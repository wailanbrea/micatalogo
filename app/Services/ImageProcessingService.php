<?php

namespace App\Services;

use App\Enums\ProductImageProcessingStatus;
use App\Jobs\ProcessProductImageJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;

class ImageProcessingService
{
    /**
     * Allowed MIME types for uploaded product images.
     */
    protected array $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
    ];

    public function validateImage(UploadedFile $file): void
    {
        $maxSizeBytes = (int) config('catalog.uploads.max_file_size_mb', 10) * 1024 * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            throw new InvalidArgumentException('La imagen excede el tamaño máximo permitido de '.config('catalog.uploads.max_file_size_mb', 10).' MB.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, $this->allowedMimes, true)) {
            throw new InvalidArgumentException("El formato de archivo ({$mime}) no es compatible. Formatos permitidos: JPG, PNG, WEBP, AVIF.");
        }

        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw new InvalidArgumentException('El archivo no es una imagen válida o está corrupto.');
        }

        [$width, $height] = $imageInfo;
        $pixels = $width * $height;
        $maxPixels = (int) config('catalog.uploads.max_input_pixels', 60_000_000);
        if ($pixels > $maxPixels) {
            throw new InvalidArgumentException("La resolución de la imagen ({$width}x{$height}) excede el límite máximo de 60 megapíxeles.");
        }
    }

    public function storeTempAndDispatch(Product $product, UploadedFile $file): ProductImage
    {
        $this->validateImage($file);

        $tempFileName = Str::ulid().'.'.($file->guessExtension() ?: 'bin');
        $tempPath = Storage::disk('temp')->putFileAs('', $file, $tempFileName);

        $nextSort = ($product->images()->max('sort_order') ?? -1) + 1;
        $checksum = hash_file('sha256', $file->getRealPath());

        $productImage = $product->images()->create([
            'source' => 'manual',
            'object_key' => "temp/{$tempFileName}",
            'thumbnail_object_key' => null,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'width' => 0,
            'height' => 0,
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => $checksum,
            'sort_order' => $nextSort,
            'processing_status' => ProductImageProcessingStatus::Pending,
        ]);

        ProcessProductImageJob::dispatch($productImage->id, $tempPath)->afterCommit();

        return $productImage;
    }

    public function storeRemoteAndDispatch(Product $product, string $bytes, string $mimeType, string $source = 'web_search'): ProductImage
    {
        $maxSizeBytes = (int) config('catalog.uploads.max_file_size_mb', 10) * 1024 * 1024;
        if (strlen($bytes) > $maxSizeBytes) {
            throw new InvalidArgumentException('La imagen externa excede el tamaño máximo permitido.');
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if ($imageInfo === false || ! in_array((string) ($imageInfo['mime'] ?? $mimeType), $this->allowedMimes, true)) {
            throw new InvalidArgumentException('La respuesta externa no contiene una imagen compatible.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if (($width * $height) > (int) config('catalog.uploads.max_input_pixels', 60_000_000)) {
            throw new InvalidArgumentException('La resolución de la imagen externa es demasiado grande.');
        }

        $extension = match ((string) ($imageInfo['mime'] ?? $mimeType)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'bin',
        };
        $tempPath = 'product-images/'.Str::ulid().'.'.$extension;
        Storage::disk('temp')->put($tempPath, $bytes);

        $productImage = $product->images()->create([
            'source' => $source,
            'object_key' => "temp/{$tempPath}",
            'thumbnail_object_key' => null,
            'mime_type' => (string) ($imageInfo['mime'] ?? $mimeType),
            'width' => 0,
            'height' => 0,
            'size_bytes' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
            'sort_order' => ($product->images()->max('sort_order') ?? -1) + 1,
            'processing_status' => ProductImageProcessingStatus::Pending,
        ]);

        ProcessProductImageJob::dispatch($productImage->id, $tempPath);

        return $productImage;
    }

    public function processAndStoreShopLogo(Shop $shop, UploadedFile $file, MediaStorageService $mediaStorage): string
    {
        $this->validateImage($file);

        $image = Image::read($file->getRealPath());

        // Square crop 400x400 for crisp logo on high-dpi displays
        $image->cover(width: 400, height: 400);
        $encoded = $image->toWebp(85);
        $bytes = (string) $encoded;

        $checksum = hash('sha256', $bytes);
        $objectKey = $mediaStorage->buildShopLogoObjectKey($shop->public_id, $checksum);

        // Delete previous logo from storage if exists
        $this->deleteShopLogo($shop, $mediaStorage);

        $mediaStorage->disk()->put($objectKey, $bytes, 'public');

        return $objectKey;
    }

    public function deleteShopLogo(Shop $shop, MediaStorageService $mediaStorage): void
    {
        if ($shop->logo_object_key && $mediaStorage->disk()->exists($shop->logo_object_key)) {
            $mediaStorage->disk()->delete($shop->logo_object_key);
        }
    }

    public function processAndStoreShopCover(Shop $shop, UploadedFile $file, MediaStorageService $mediaStorage): string
    {
        $this->validateImage($file);

        $image = Image::read($file->getRealPath());
        $image->cover(width: 1400, height: 520);
        $bytes = (string) $image->toWebp(84);
        $checksum = hash('sha256', $bytes);
        $objectKey = $mediaStorage->buildShopCoverObjectKey($shop->public_id, $checksum);

        $this->deleteShopCover($shop, $mediaStorage);
        $mediaStorage->disk()->put($objectKey, $bytes, 'public');

        return $objectKey;
    }

    public function deleteShopCover(Shop $shop, MediaStorageService $mediaStorage): void
    {
        if ($shop->cover_object_key && $mediaStorage->disk()->exists($shop->cover_object_key)) {
            $mediaStorage->disk()->delete($shop->cover_object_key);
        }
    }
}
