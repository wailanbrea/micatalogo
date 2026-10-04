<?php

namespace App\Services;

use Intervention\Image\Laravel\Facades\Image;

class ImageDerivativeService
{
    /**
     * @return array{main_bytes: string, thumb_bytes: string, checksum: string, width: int, height: int, size_bytes: int}
     */
    public function render(string $rawBinary): array
    {
        $image = Image::read($rawBinary);

        $main = clone $image;
        $main->scaleDown(
            width: (int) config('catalog.images.main_max_width', 1600),
            height: (int) config('catalog.images.main_max_height', 1600),
        );
        $mainBytes = (string) $main->toWebp((int) config('catalog.images.webp_quality', 80));

        $thumbnail = clone $image;
        $thumbnail->cover(
            width: (int) config('catalog.images.thumbnail_width', 480),
            height: (int) config('catalog.images.thumbnail_height', 480),
        );
        $thumbBytes = (string) $thumbnail->toWebp((int) config('catalog.images.thumbnail_quality', 75));

        return [
            'main_bytes' => $mainBytes,
            'thumb_bytes' => $thumbBytes,
            'checksum' => hash('sha256', $mainBytes),
            'width' => $main->width(),
            'height' => $main->height(),
            'size_bytes' => strlen($mainBytes),
        ];
    }

    /**
     * @param  array{main_bytes: string, thumb_bytes: string}  $derivatives
     */
    public function store(array $derivatives, string $mainKey, string $thumbKey, MediaStorageService $mediaStorage): void
    {
        $disk = $mediaStorage->disk();
        if (! $disk->exists($mainKey)) {
            $disk->put($mainKey, $derivatives['main_bytes'], 'public');
        }
        if (! $disk->exists($thumbKey)) {
            $disk->put($thumbKey, $derivatives['thumb_bytes'], 'public');
        }
    }
}
