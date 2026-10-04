<?php

namespace App\Jobs;

use App\Enums\ProductImageProcessingStatus;
use App\Models\ProductImage;
use App\Services\ImageDerivativeService;
use App\Services\MediaStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public function handle(MediaStorageService $mediaStorage, ?ImageDerivativeService $derivatives = null): void
    {
        $derivatives ??= app(ImageDerivativeService::class);
        $productImage = ProductImage::with('product')->find($this->productImageId);
        if (! $productImage) {
            $this->deleteTempFile(Storage::disk('temp'));

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

            $rendered = $derivatives->render($rawBinary);
            $checksum = $rendered['checksum'];
            $mainKey = $mediaStorage->buildProductObjectKey($productImage->product->public_id, 'main', $checksum);
            $thumbKey = $mediaStorage->buildProductObjectKey($productImage->product->public_id, 'thumb', $checksum);

            $derivatives->store($rendered, $mainKey, $thumbKey, $mediaStorage);

            $productImage->update([
                'object_key' => $mainKey,
                'thumbnail_object_key' => $thumbKey,
                'mime_type' => 'image/webp',
                'width' => $rendered['width'],
                'height' => $rendered['height'],
                'size_bytes' => $rendered['size_bytes'],
                'checksum_sha256' => $checksum,
                'processing_status' => ProductImageProcessingStatus::Ready,
            ]);

            // 6. Purge temporary raw upload
            $this->deleteTempFile($tempDisk);
        } catch (Throwable $e) {
            Log::error("Error procesando imagen de producto [{$this->productImageId}]: ".$e->getMessage(), [
                'exception' => $e,
                'tempPath' => $this->tempPath,
            ]);

            $productImage->update(['processing_status' => ProductImageProcessingStatus::Failed]);
            $this->deleteTempFile($tempDisk);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $productImage = ProductImage::find($this->productImageId);
        $productImage?->update(['processing_status' => ProductImageProcessingStatus::Failed]);

        $this->deleteTempFile(Storage::disk('temp'));
    }

    private function deleteTempFile(Filesystem $tempDisk): void
    {
        try {
            if ($tempDisk->exists($this->tempPath)) {
                $tempDisk->delete($this->tempPath);
            }
        } catch (Throwable $e) {
            Log::warning("No se pudo eliminar el archivo temporal de imagen [{$this->productImageId}]: ".$e->getMessage(), [
                'tempPath' => $this->tempPath,
            ]);
        }
    }
}
