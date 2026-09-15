<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MediaStorageService
{
    public function isR2Configured(): bool
    {
        $key = config('filesystems.disks.r2.key');
        $secret = config('filesystems.disks.r2.secret');
        $bucket = config('filesystems.disks.r2.bucket');
        $endpoint = config('filesystems.disks.r2.endpoint');

        return ! empty($key) && ! empty($secret) && ! empty($bucket) && ! empty($endpoint);
    }

    public function diskName(): string
    {
        return $this->isR2Configured() ? 'r2' : 'public';
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function tempDisk(): Filesystem
    {
        return Storage::disk('temp');
    }

    public function url(string $objectKey): string
    {
        if (str_starts_with($objectKey, 'http://localhost/images/') || str_starts_with($objectKey, 'http://127.0.0.1/images/')) {
            $path = parse_url($objectKey, PHP_URL_PATH);

            return asset(ltrim($path, '/'));
        }

        if (str_starts_with($objectKey, '/images/') || str_starts_with($objectKey, 'images/catalog/')) {
            return asset(ltrim($objectKey, '/'));
        }

        if (str_starts_with($objectKey, 'http://') || str_starts_with($objectKey, 'https://')) {
            return $objectKey;
        }

        if ($this->isR2Configured()) {
            $baseUrl = rtrim(config('filesystems.disks.r2.url') ?: 'https://media.micatalogo.com', '/');

            if (app()->isProduction() && str_contains($baseUrl, 'r2.dev')) {
                throw new InvalidArgumentException('El dominio de medios en producción no puede usar el subdominio r2.dev.');
            }

            return $baseUrl.'/'.ltrim($objectKey, '/');
        }

        return Storage::disk('public')->url($objectKey);
    }

    public function buildProductObjectKey(string $publicId, string $variant, string $checksum): string
    {
        $hash = substr($checksum, 0, 16);

        return "products/{$publicId}/{$variant}-{$hash}.webp";
    }

    public function buildShopLogoObjectKey(string $publicId, string $checksum): string
    {
        $hash = substr($checksum, 0, 16);

        return "shops/{$publicId}/logo-{$hash}.webp";
    }

    public function healthCheck(): array
    {
        $disk = $this->disk();
        $testFile = '__health_check_'.Str::random(8).'.tmp';

        try {
            $disk->put($testFile, 'ok');
            $read = $disk->get($testFile);
            $disk->delete($testFile);

            return [
                'status' => 'healthy',
                'disk' => $this->diskName(),
                'r2_configured' => $this->isR2Configured(),
                'readable' => $read === 'ok',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'disk' => $this->diskName(),
                'r2_configured' => $this->isR2Configured(),
                'error' => $e->getMessage(),
            ];
        }
    }
}
