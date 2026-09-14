<?php

use App\Services\MediaStorageService;
use Illuminate\Support\Facades\Storage;

test('r2 and temp disks are registered in filesystems configuration', function () {
    $disks = config('filesystems.disks');

    expect($disks)->toHaveKey('r2')
        ->and($disks['r2']['driver'])->toBe('s3')
        ->and($disks)->toHaveKey('temp')
        ->and($disks['temp']['visibility'])->toBe('private')
        ->and($disks['temp']['root'])->toBe(storage_path('app/private/temp'));
});

test('media storage service detects when r2 is configured vs unconfigured', function () {
    $service = app(MediaStorageService::class);

    // Default without credentials should be unconfigured and use public fallback
    config()->set('filesystems.disks.r2.key', null);
    config()->set('filesystems.disks.r2.secret', null);
    config()->set('filesystems.disks.r2.bucket', null);
    config()->set('filesystems.disks.r2.endpoint', null);

    expect($service->isR2Configured())->toBeFalse()
        ->and($service->diskName())->toBe('public');

    // When configured with credentials
    config()->set('filesystems.disks.r2.key', 'test_key');
    config()->set('filesystems.disks.r2.secret', 'test_secret');
    config()->set('filesystems.disks.r2.bucket', 'micatalogo-test');
    config()->set('filesystems.disks.r2.endpoint', 'https://account.r2.cloudflarestorage.com');

    expect($service->isR2Configured())->toBeTrue()
        ->and($service->diskName())->toBe('r2');
});

test('media storage service resolves urls with custom domain and prevents r2dev in production', function () {
    $service = app(MediaStorageService::class);

    // Direct remote URLs are passed through
    expect($service->url('https://images.unsplash.com/sample.webp'))
        ->toBe('https://images.unsplash.com/sample.webp');

    // With R2 custom domain configured
    config()->set('filesystems.disks.r2.key', 'test_key');
    config()->set('filesystems.disks.r2.secret', 'test_secret');
    config()->set('filesystems.disks.r2.bucket', 'micatalogo-test');
    config()->set('filesystems.disks.r2.endpoint', 'https://account.r2.cloudflarestorage.com');
    config()->set('filesystems.disks.r2.url', 'https://media.micatalogo.com');

    expect($service->url('products/01HXYZ/main.webp'))
        ->toBe('https://media.micatalogo.com/products/01HXYZ/main.webp');

    // In production, r2.dev domain must be rejected
    config()->set('filesystems.disks.r2.url', 'https://pub-abc123xyz.r2.dev');
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => $service->url('products/01HXYZ/main.webp'))
        ->toThrow(InvalidArgumentException::class, 'El dominio de medios en producción no puede usar el subdominio r2.dev.');
});

test('media storage service generates deterministic content-versioned object keys', function () {
    $service = app(MediaStorageService::class);

    $checksum = hash('sha256', 'sample image content');
    $key = $service->buildProductObjectKey('01JABCD1234', 'main', $checksum);

    expect($key)->toBe('products/01JABCD1234/main-'.substr($checksum, 0, 16).'.webp');
});

test('media storage service performs storage health check successfully', function () {
    Storage::fake('public');

    config()->set('filesystems.disks.r2.key', null);
    $service = app(MediaStorageService::class);

    $health = $service->healthCheck();

    expect($health['status'])->toBe('healthy')
        ->and($health['readable'])->toBeTrue();
});
