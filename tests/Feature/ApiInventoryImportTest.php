<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('a Pro owner can preview and confirm an inventory file from the API', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $token = $owner->createToken('inventory-import', ['catalog:read'])->plainTextToken;
    $file = UploadedFile::fake()->createWithContent(
        'inventario.csv',
        "nombre,codigo,marca,categoria,precio,costo,stock,atributos\nPerfume Demo,SKU-01,Rasasi,Fragancias,2800,1600,12,Concentración=EDP;Presentación=100 ml\nFila incompleta,,,,,,,\n"
    );

    $preview = $this->withToken($token)
        ->post('/api/v1/shops/'.$shop->public_id.'/inventory-import/preview', ['file' => $file]);

    $preview->assertOk()
        ->assertJsonPath('valid_rows', 1)
        ->assertJsonPath('invalid_rows', 1)
        ->assertJsonPath('rows.0.name', 'Perfume Demo');

    $rows = $preview->json('rows');
    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', ['rows' => $rows])
        ->assertCreated()
        ->assertJsonPath('imported', 1)
        ->assertJsonPath('quota.product_count', 1);

    expect(Product::where('shop_id', $shop->id)->first())
        ->name->toBe('Perfume Demo');
    $this->assertDatabaseHas('product_inventories', ['stock_quantity' => 12]);
    $this->assertDatabaseHas('product_attribute_values', ['value' => 'EDP']);
});

test('a free account can preview and confirm inventory import from the API', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Free]);
    $shop = Shop::factory()->for($owner)->create();
    $token = $owner->createToken('inventory-import', ['catalog:read'])->plainTextToken;
    $file = UploadedFile::fake()->createWithContent('inventario.csv', "nombre,precio\nProducto,100\n");

    $this->withToken($token)
        ->post('/api/v1/shops/'.$shop->public_id.'/inventory-import/preview', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('valid_rows', 1);

    $rows = $this->withToken($token)
        ->post('/api/v1/shops/'.$shop->public_id.'/inventory-import/preview', ['file' => $file])
        ->json('rows');

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', ['rows' => $rows])
        ->assertCreated()
        ->assertJsonPath('imported', 1);
});

test('free basic and pro plans expose bulk inventory import', function () {
    foreach ([UserPlan::Free, UserPlan::Premium, UserPlan::Pro] as $plan) {
        expect(config('catalog.plans.'.$plan->value.'.features'))->toContain('bulk_import');
    }
});
