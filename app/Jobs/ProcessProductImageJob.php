<?php

namespace App\Jobs;

use App\Enums\ProductImageProcessingStatus;
use App\Models\ProductImage;
use App\Services\MediaStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class ProcessProductImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $productImageId,
        public string $tempPath
    ) {}

    public function handle(MediaStorageService $mediaStorage): void
    {
        $productImage = ProductImage::with('product')->find($this->productImageId);
        if (! $productImage) {
            Storage::disk('temp')->delete($this->tempPath);

            return;
        }

        $productImage->update(['processing_status' => ProductImageProcessingStatus::Processing]);

        $tempDisk = Storage::disk('temp');
        if (! $tempDisk->exists($this->tempPath)) {
            $productImage->update(['processing_status' => ProductImageProcessingStatus::Failed]);

            return;
        }

        try {
            $rawBinary = $tempDisk->get($this->tempPath);

            // Decode image using Intervention Image (with auto-orientation & metadata stripping via re-encoding)
            $image = Image::read($rawBinary);

            // 1. Generate Main Derivative (scale down to max 1600x1600 preserving aspect ratio)
            $mainWidth = (int) config('catalog.images.main_max_width', 1600);
            $mainHeight = (int) config('catalog.images.main_max_height', 1600);
            $mainQuality = (int) config('catalog.images.webp_quality', 80);

            $main = clone $image;
            $main->scaleDown(width: $mainWidth, height: $mainHeight);
            $mainEncoded = $main->toWebp($mainQuality);
            $mainBytes = (string) $mainEncoded;

            // 2. Generate Thumbnail Derivative (480x480 crop/cover for grids)
            $thumbWidth = (int) config('catalog.images.thumbnail_width', 480);
            $thumbHeight = (int) config('catalog.images.thumbnail_height', 480);
            $thumbQuality = (int) config('catalog.images.thumbnail_quality', 75);

            $thumbnail = clone $image;
            $thumbnail->cover(width: $thumbWidth, height: $thumbHeight);
            $thumbEncoded = $thumbnail->toWebp($thumbQuality);
            $thumbBytes = (string) $thumbEncoded;

            // 3. Compute Checksums & Content-Versioned Keys
            $checksum = hash('sha256', $mainBytes);
            $mainKey = $mediaStorage->buildProductObjectKey($productImage->product->public_id, 'main', $checksum);
            $thumbKey = $mediaStorage->buildProductObjectKey($productImage->product->public_id, 'thumb', $checksum);

            // 4. Upload Persistent Derivatives to Media Storage Disk (R2 / fallback)
            $mediaDisk = $mediaStorage->disk();
            $mediaDisk->put($mainKey, $mainBytes, 'public');
            $mediaDisk->put($thumbKey, $thumbBytes, 'public');

            // 5. Update Database Record with durable metadata
            $productImage->update([
                'object_key' => $mainKey,
                'thumbnail_object_key' => $thumbKey,
                'mime_type' => 'image/webp',
                'width' => $main->width(),
                'height' => $main->height(),
                'size_bytes' => strlen($mainBytes),
                'checksum_sha256' => $checksum,
                'processing_status' => ProductImageProcessingStatus::Ready,
            ]);

            // 6. Purge temporary raw upload
            $tempDisk->delete($this->tempPath);
        } catch (Throwable $e) {
            Log::error("Error procesando imagen de producto [{$this->productImageId}]: ".$e->getMessage(), [
                'exception' => $e,
                'tempPath' => $this->tempPath,
            ]);

            $productImage->update(['processing_status' => ProductImageProcessingStatus::Failed]);
            $tempDisk->delete($this->tempPath);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $productImage = ProductImage::find($this->productImageId);
        $productImage?->update(['processing_status' => ProductImageProcessingStatus::Failed]);

        Storage::disk('temp')->delete($this->tempPath);
    }
}
