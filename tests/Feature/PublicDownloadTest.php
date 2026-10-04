<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('download page lists the current Android release and Play Store notice', function () {
    config()->set('bspos.android_update', [
        'version_code' => 7,
        'version_name' => '1.0.6',
        'minimum_supported_version_code' => 7,
        'apk_url' => 'https://micatalogo.bsolutions.dev/downloads/bspos-1.0.6.apk',
        'apk_sha256' => str_repeat('a', 64),
        'release_notes' => 'Actualización obligatoria.',
        'release_date' => '2026-10-04',
    ]);
    Cache::put('android-downloads.7', 12);

    $this->get(route('downloads.index'))
        ->assertOk()
        ->assertSee('MiCatalogo 1.0.6')
        ->assertSee('12 descargas')
        ->assertSee('Próximamente en Play Store')
        ->assertSee(route('downloads.android', 7));
});

test('Android download increments the public counter and redirects to the APK', function () {
    config()->set('bspos.android_update', [
        'version_code' => 7,
        'version_name' => '1.0.6',
        'minimum_supported_version_code' => 7,
        'apk_url' => 'https://micatalogo.bsolutions.dev/downloads/bspos-1.0.6.apk',
        'apk_sha256' => str_repeat('a', 64),
        'release_notes' => '',
        'release_date' => '2026-10-04',
    ]);

    $this->get(route('downloads.android', 7))
        ->assertRedirect('https://micatalogo.bsolutions.dev/downloads/bspos-1.0.6.apk');

    expect(Cache::get('android-downloads.7'))->toBe(1);
});
