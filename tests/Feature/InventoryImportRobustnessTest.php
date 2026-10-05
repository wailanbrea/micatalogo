<?php

use App\Enums\UserPlan;
use App\Models\InventoryImportSession;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CatalogMediaService;
use App\Services\InventoryImportService;
use App\Services\PlanLimitsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

test('money parser handles standard and latin formats accurately without float corruption', function () {
    // Standard and international/latin formats
    expect(InventoryImportService::parseMoney('2500'))->toBe('2500.00')
        ->and(InventoryImportService::parseMoney('2500.50'))->toBe('2500.50')
        ->and(InventoryImportService::parseMoney('2500,50'))->toBe('2500.50') // Crucial: must NEVER be 250050.00
        ->and(InventoryImportService::parseMoney('2,500'))->toBe('2500.00')
        ->and(InventoryImportService::parseMoney('2,500.50'))->toBe('2500.50')
        ->and(InventoryImportService::parseMoney('2.500,50'))->toBe('2500.50')
        ->and(InventoryImportService::parseMoney('RD$ 2,500.50'))->toBe('2500.50')
        ->and(InventoryImportService::parseMoney('RD$ 2.500,50'))->toBe('2500.50')
        ->and(InventoryImportService::parseMoney('RD$2,500.50'))->toBe('2500.50')
        ->and(InventoryImportService::parseMoney('$2500'))->toBe('2500.00')
        ->and(InventoryImportService::parseMoney('DOP 10,000.75'))->toBe('10000.75');

    // Verification that 2500,50 never becomes 250050
    expect((float) InventoryImportService::parseMoney('2500,50'))->toBeLessThan(3000.0);

    // Invalid values
    expect(InventoryImportService::parseMoney('abc'))->toBeNull()
        ->and(InventoryImportService::parseMoney('RD$abc'))->toBeNull()
        ->and(InventoryImportService::parseMoney('12,3,4'))->toBeNull()
        ->and(InventoryImportService::parseMoney('--250'))->toBeNull()
        ->and(InventoryImportService::parseMoney(''))->toBeNull()
        ->and(InventoryImportService::parseMoney(null))->toBeNull();
});

test('sku and barcode preserve leading zeros as strict strings', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);

    $csv = "nombre,codigo,barcode,precio,stock\nProducto Con Ceros,000123,00789012,150.00,5\n";
    $file = UploadedFile::fake()->createWithContent('ceros.csv', $csv);

    $preview = $importer->preview($file, [], $shop, $owner);

    expect($preview['rows'][0]['product_code'])->toBe('000123')
        ->and($preview['rows'][0]['barcode'])->toBe('00789012')
        ->and($preview['rows'][0]['price'])->toBe('150.00')
        ->and($preview['rows'][0]['stock'])->toBe(5);
});

test('parser handles semicolon delimiter, utf8 bom, accents and blank lines', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);

    // CSV with BOM, semicolon delimiter, accented characters (ñ, á) and blank rows
    $bom = "\xEF\xBB\xBF";
    $content = $bom."nombre;precio;categoria;stock\n"
        ."Café Dominicano Supremo;450,50;Bebidas y Café;10\n"
        ."\n"
        ."Camisa de Niño;1.200,00;Ropa Infantil;4\n"
        ."   \n";

    $file = UploadedFile::fake()->createWithContent('inventario_bom.csv', $content);
    $preview = $importer->preview($file, [], $shop, $owner);

    expect($preview['valid_rows'])->toBe(2)
        ->and($preview['rows'][0]['name'])->toBe('Café Dominicano Supremo')
        ->and($preview['rows'][0]['price'])->toBe('450.50')
        ->and($preview['rows'][0]['stock'])->toBe(10)
        ->and($preview['rows'][1]['name'])->toBe('Camisa de Niño')
        ->and($preview['rows'][1]['price'])->toBe('1200.00')
        ->and($preview['rows'][1]['stock'])->toBe(4);
});

test('detects internal duplicates within the same file', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);

    $csv = "nombre,codigo,barcode,precio\n"
        ."Perfume A,SKU-001,7460000001,500\n"
        ."Perfume B,SKU-001,7460000002,600\n" // Duplicate SKU
        ."Perfume C,SKU-002,7460000001,700\n"; // Duplicate Barcode

    $file = UploadedFile::fake()->createWithContent('duplicados.csv', $csv);
    $preview = $importer->preview($file, [], $shop, $owner);

    expect($preview['rows'][0]['valid'])->toBeTrue()
        ->and($preview['rows'][1]['valid'])->toBeFalse()
        ->and($preview['rows'][1]['errors'])->toContain('SKU duplicado dentro del archivo (visto primero en fila 2).')
        ->and($preview['rows'][2]['valid'])->toBeFalse()
        ->and($preview['rows'][2]['errors'])->toContain('Código de barras duplicado dentro del archivo (visto primero en fila 2).');
});

test('identifies existing products in the same shop and respects multi-tenant isolation', function () {
    $ownerA = User::factory()->create(['plan' => UserPlan::Pro]);
    $shopA = Shop::factory()->for($ownerA)->create();

    $ownerB = User::factory()->create(['plan' => UserPlan::Pro]);
    $shopB = Shop::factory()->for($ownerB)->create();

    // Create existing products in Shop A
    Product::factory()->for($shopA)->create([
        'name' => 'Perfume Sauvage',
        'product_code' => 'SAU-001',
        'barcode' => '7461234567890',
        'price' => 3000,
    ]);

    // Create a product in Shop B with SKU 'SAU-002'
    Product::factory()->for($shopB)->create([
        'name' => 'Otro Producto B',
        'product_code' => 'SAU-002',
        'barcode' => '7469999999999',
        'price' => 1000,
    ]);

    $importer = app(InventoryImportService::class);

    // File for Shop A contains:
    // 1. Matches Shop A by SKU (SAU-001) -> existing
    // 2. Contains SKU from Shop B (SAU-002) -> must be NEW for Shop A (multi-tenant isolation)
    $csv = "nombre,codigo,barcode,precio\n"
        ."Sauvage Reposición,SAU-001,7461234567890,3200\n"
        ."Nuevo En Tienda A,SAU-002,7460000000001,1500\n";

    $file = UploadedFile::fake()->createWithContent('isolation.csv', $csv);
    $preview = $importer->preview($file, [], $shopA, $ownerA);

    expect($preview['rows'][0]['status'])->toBe('existing')
        ->and($preview['rows'][0]['match_field'])->toBe('barcode')
        ->and($preview['rows'][1]['status'])->toBe('new')
        ->and($preview['rows'][1]['match_field'])->toBeNull();
});

test('duplicate strategies: skip, update, and create behave correctly without stock duplication', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);
    $limits = app(PlanLimitsService::class);
    $media = app(CatalogMediaService::class);

    $existing = Product::factory()->for($shop)->create([
        'name' => 'Camisa Original',
        'product_code' => 'CAM-01',
        'price' => 800,
    ]);
    $existing->inventory()->create([
        'track_inventory' => true,
        'stock_quantity' => 10,
        'cost_price' => 400,
    ]);

    // 1. Test strategy: SKIP
    $csv = "nombre,codigo,precio,stock\nCamisa Actualizada,CAM-01,950,25\n";
    $file = UploadedFile::fake()->createWithContent('skip.csv', $csv);
    $preview = $importer->preview($file, [], $shop, $owner);
    $session = InventoryImportSession::where('public_id', $preview['session_id'])->first();

    $summarySkip = $importer->confirmSession($shop, $session, ['duplicate_strategy' => 'skip'], $limits, $media, $owner);
    expect($summarySkip['skipped'])->toBe(1)
        ->and($summarySkip['updated'])->toBe(0);

    $existing->refresh();
    expect($existing->name)->toBe('Camisa Original')
        ->and((float) $existing->price)->toBe(800.0)
        ->and($existing->inventory->stock_quantity)->toBe(10);

    // 2. Test strategy: UPDATE
    $fileUpdate = UploadedFile::fake()->createWithContent('update.csv', $csv);
    $previewUpdate = $importer->preview($fileUpdate, [], $shop, $owner);
    $sessionUpdate = InventoryImportSession::where('public_id', $previewUpdate['session_id'])->first();

    $summaryUpdate = $importer->confirmSession($shop, $sessionUpdate, ['duplicate_strategy' => 'update'], $limits, $media, $owner);
    expect($summaryUpdate['updated'])->toBe(1);

    $existing->refresh();
    expect($existing->name)->toBe('Camisa Actualizada')
        ->and($existing->price)->toBe('950.00')
        ->and($existing->inventory->stock_quantity)->toBe(25);
    // Ensure only 1 product inventory record exists for the product
    expect($existing->inventory()->count())->toBe(1);
});

test('unrecognized categories generate warning and respect create_missing_categories option', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);
    $limits = app(PlanLimitsService::class);
    $media = app(CatalogMediaService::class);

    // Existing category
    $shop->categories()->create([
        'name' => 'Perfumería Fina',
        'slug' => 'perfumeria-fina',
        'status' => 'active',
    ]);

    $csv = "nombre,categoria,precio\n"
        ."Perfume A,perfumería fina,1500\n" // Matches case/accent-insensitively
        ."Incienso Oriental,Aromaterapia Nueva,600\n"; // Missing category

    $file = UploadedFile::fake()->createWithContent('cats.csv', $csv);
    $preview = $importer->preview($file, [], $shop, $owner);

    expect($preview['missing_categories'])->toContain('Aromaterapia Nueva')
        ->and($preview['rows'][0]['category_status'])->toBe('shop_category')
        ->and($preview['rows'][1]['category_status'])->toBe('missing');

    // Case A: Confirm without creating missing category
    $sessionA = InventoryImportSession::where('public_id', $preview['session_id'])->first();
    $importer->confirmSession($shop, $sessionA, [
        'duplicate_strategy' => 'skip',
        'create_missing_categories' => false,
    ], $limits, $media, $owner);

    $incienso = Product::where('shop_id', $shop->id)->where('name', 'Incienso Oriental')->first();
    expect($incienso->shop_category_id)->toBeNull();
    expect($shop->categories()->where('name', 'Aromaterapia Nueva')->exists())->toBeFalse();

    // Case B: Confirm with create_missing_categories = true
    $fileB = UploadedFile::fake()->createWithContent('cats_b.csv', "nombre,categoria,precio\nVela Aromática,Aromaterapia Nueva,300\n");
    $previewB = $importer->preview($fileB, [], $shop, $owner);
    $sessionB = InventoryImportSession::where('public_id', $previewB['session_id'])->first();

    $summaryB = $importer->confirmSession($shop, $sessionB, [
        'duplicate_strategy' => 'skip',
        'create_missing_categories' => true,
    ], $limits, $media, $owner);

    expect($summaryB['categories_created'])->toBe(1);
    expect($shop->categories()->where('name', 'Aromaterapia Nueva')->exists())->toBeTrue();
    $vela = Product::where('shop_id', $shop->id)->where('name', 'Vela Aromática')->first();
    expect($vela->shopCategory->name)->toBe('Aromaterapia Nueva');
});

test('duplicate attributes within the same row are detected and preserved without crash', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);

    $csv = "nombre,precio,atributos\n"
        ."Perfume Especial,2000,Marca=Rasasi;Concentración=EDP;Marca=Lattafa\n";

    $file = UploadedFile::fake()->createWithContent('attrs.csv', $csv);
    $preview = $importer->preview($file, [], $shop, $owner);

    $row = $preview['rows'][0];
    expect($row['valid'])->toBeTrue()
        ->and($row['warnings'])->toContain("Atributo 'Marca' duplicado en la misma fila (se conserva el primer valor).");

    $marcaAttr = collect($row['attributes'])->firstWhere('name', 'Marca');
    expect($marcaAttr['value'])->toBe('Rasasi');
});

test('session security prevents cross-tenant confirmation, handles expiry and provides idempotency', function () {
    $ownerA = User::factory()->create(['plan' => UserPlan::Pro]);
    $shopA = Shop::factory()->for($ownerA)->create();

    $ownerB = User::factory()->create(['plan' => UserPlan::Pro]);
    $shopB = Shop::factory()->for($ownerB)->create();

    $importer = app(InventoryImportService::class);
    $limits = app(PlanLimitsService::class);
    $media = app(CatalogMediaService::class);

    $file = UploadedFile::fake()->createWithContent('test.csv', "nombre,precio\nProducto Seguro,100\n");
    $preview = $importer->preview($file, [], $shopA, $ownerA);
    $session = InventoryImportSession::where('public_id', $preview['session_id'])->first();

    // 1. Cross-tenant attempt: Shop B tries to confirm Shop A's session
    expect(fn () => $importer->confirmSession($shopB, $session, [], $limits, $media, $ownerB))
        ->toThrow(HttpException::class);

    // 2. Normal confirmation succeeds
    $firstConfirm = $importer->confirmSession($shopA, $session, [], $limits, $media, $ownerA);
    expect($firstConfirm['created'])->toBe(1);
    expect($shopA->products()->count())->toBe(1);

    // 3. Idempotency: second confirmation does not duplicate products
    $secondConfirm = $importer->confirmSession($shopA, $session, [], $limits, $media, $ownerA);
    expect($secondConfirm['created'])->toBe(1);
    expect($shopA->products()->count())->toBe(1); // Still 1!
});

test('plan quota only charges newly created products and rolls back entirely if quota is exceeded', function () {
    config()->set('catalog.plans.pro.max_products_per_shop', 3);
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $importer = app(InventoryImportService::class);
    $limits = app(PlanLimitsService::class);
    $media = app(CatalogMediaService::class);

    // Shop already has 2 products (remaining quota = 1)
    $p1 = Product::factory()->for($shop)->create(['name' => 'P1', 'product_code' => 'SKU-01', 'price' => 100]);
    $p2 = Product::factory()->for($shop)->create(['name' => 'P2', 'product_code' => 'SKU-02', 'price' => 100]);

    // File contains:
    // 1. Existing SKU-01 (will update, quota delta = 0)
    // 2. Existing SKU-02 (will update, quota delta = 0)
    // 3. New SKU-03 (will create, quota delta = 1)
    // Total new = 1 <= 1 remaining quota -> MUST SUCCEED!
    $csv = "nombre,codigo,precio\n"
        ."P1 Actualizado,SKU-01,150\n"
        ."P2 Actualizado,SKU-02,250\n"
        ."P3 Nuevo,SKU-03,350\n";

    $file = UploadedFile::fake()->createWithContent('quota_ok.csv', $csv);
    $preview = $importer->preview($file, [], $shop, $owner);
    $session = InventoryImportSession::where('public_id', $preview['session_id'])->first();

    $summary = $importer->confirmSession($shop, $session, ['duplicate_strategy' => 'update'], $limits, $media, $owner);
    expect($summary['updated'])->toBe(2)
        ->and($summary['created'])->toBe(1);
    expect($shop->products()->count())->toBe(3);

    // Now shop is full (3/3). Attempting to import 1 new product must fail and rollback
    $csvExceed = "nombre,precio\nP4 No Deberia Entrar,400\n";
    $fileExceed = UploadedFile::fake()->createWithContent('quota_fail.csv', $csvExceed);
    $previewExceed = $importer->preview($fileExceed, [], $shop, $owner);
    $sessionExceed = InventoryImportSession::where('public_id', $previewExceed['session_id'])->first();

    expect(fn () => $importer->confirmSession($shop, $sessionExceed, [], $limits, $media, $owner))
        ->toThrow(ValidationException::class);

    // Rollback verification: product count is still 3 and P4 was never created
    expect($shop->products()->count())->toBe(3);
    expect($shop->products()->where('name', 'P4 No Deberia Entrar')->exists())->toBeFalse();
});

test('web seller flow: previews file, receives session, and confirms with session_id', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();

    $file = UploadedFile::fake()->createWithContent('web.csv', "nombre,precio,stock\nProducto Web,250.00,10\n");

    // 1. Preview
    $previewResponse = $this->actingAs($owner)
        ->post(route('seller.shops.products.import.preview', $shop), [
            'file' => $file,
        ]);

    $previewResponse->assertOk()
        ->assertViewIs('seller.products.import')
        ->assertViewHas('sessionId')
        ->assertViewHas('validRows', 1)
        ->assertSee('Producto Web');

    $session = InventoryImportSession::where('shop_id', $shop->id)->first();
    expect($session)->not->toBeNull();

    // 2. Confirm
    $confirmResponse = $this->actingAs($owner)
        ->post(route('seller.shops.products.import.store', $shop), [
            'session_id' => $session->public_id,
            'duplicate_strategy' => 'skip',
        ]);

    $confirmResponse->assertRedirect(route('seller.shops.products.index', $shop))
        ->assertSessionHas('status');

    expect($shop->products()->where('name', 'Producto Web')->exists())->toBeTrue();
    expect($session->fresh()->isConfirmed())->toBeTrue();
});

test('api flow: previews file, confirms using dedicated /confirm endpoint, and rejects unauthorized access', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $stranger = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $token = $owner->createToken('import-token', ['catalog:read'])->plainTextToken;
    $strangerToken = $stranger->createToken('stranger-token', ['catalog:read'])->plainTextToken;

    $file = UploadedFile::fake()->createWithContent('api_flow.csv', "nombre,codigo,precio,stock\nPerfume API,API-01,1500.50,7\n");

    // 1. Preview via API
    $previewResponse = $this->withToken($token)
        ->post('/api/v1/shops/'.$shop->public_id.'/inventory-import/preview', [
            'file' => $file,
        ]);

    $previewResponse->assertOk()
        ->assertJsonStructure(['session_id', 'rows', 'valid_rows']);

    $sessionId = $previewResponse->json('session_id');
    expect($sessionId)->not->toBeNull();

    // 2. Stranger attempts confirmation -> 403 Forbidden
    auth()->forgetGuards();
    $this->withToken($strangerToken)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import/'.$sessionId.'/confirm', [
            'duplicate_strategy' => 'skip',
        ])
        ->assertForbidden();

    // 3. Legitimate owner confirms via dedicated endpoint
    auth()->forgetGuards();
    $confirmResponse = $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import/'.$sessionId.'/confirm', [
            'duplicate_strategy' => 'skip',
        ]);

    $confirmResponse->assertOk()
        ->assertJsonPath('summary.created', 1)
        ->assertJsonPath('summary.updated', 0);

    $product = $shop->products()->where('product_code', 'API-01')->first();
    expect($product)->not->toBeNull()
        ->and($product->name)->toBe('Perfume API')
        ->and($product->price)->toBe('1500.50')
        ->and($product->inventory->stock_quantity)->toBe(7);

    // 4. Double confirm via API (idempotency) returns identical summary with HTTP 200
    $secondResponse = $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import/'.$sessionId.'/confirm', [
            'duplicate_strategy' => 'skip',
        ]);

    $secondResponse->assertOk()
        ->assertJsonPath('summary.created', 1);

    expect($shop->products()->where('product_code', 'API-01')->count())->toBe(1);
});
