<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mobile shop manifest exposes the configured business timezone', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create([
        'operational_settings' => ['timezone' => 'America/New_York'],
    ]);

    $this->withToken($owner->createToken('timezone-test', ['*'])->plainTextToken)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.id', $shop->public_id)
        ->assertJsonPath('0.business_timezone', 'America/New_York');
});
