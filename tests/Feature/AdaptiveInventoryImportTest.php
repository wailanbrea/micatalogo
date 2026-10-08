<?php

use App\Enums\UserPlan;
use App\Jobs\ProcessImportedProductImageJob;
use App\Models\InventoryImportSession;
use App\Models\Shop;
use App\Models\User;
use App\Services\CatalogMediaService;
use App\Services\InventoryImport\ColumnDetector;
use App\Services\InventoryImport\WorkbookReader;
use App\Services\InventoryImportService;
use App\Services\PlanLimitsService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InventoryWorkbookFixture;

uses(RefreshDatabase::class);
beforeEach(function () {
    Queue::fake();
});

function adaptiveWorkbook(string $format = 'xlsx', int $products = 2): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'inventory-fixture-');
    InventoryWorkbookFixture::write($path, $products, $format);

    return new UploadedFile($path, 'inventario-anonimo.'.$format, null, null, true);
}

test('row eight workbook detects actual second sheet and unit values while preserving EAN UPC SKU', function (string $format) {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $result = app(InventoryImportService::class)->preview(adaptiveWorkbook($format), [], $shop, $owner);
    expect($result['header_row'])->toBe(8)->and($result['sheet']['name'])->toBe('Existencias')->and($result['sheet']['index'])->toBe(1)
        ->and($result['valid_rows'])->toBe(2)->and($result['mapping']['barcode'])->toBe('codigo del producto')
        ->and($result['mapping']['stock'])->toBe('cant')->and($result['mapping']['cost_price'])->toBe('costo unitario')
        ->and($result['mapping']['price'])->toBe('precio unitario')->and($result['rows'][0]['line'])->toBe(9)
        ->and($result['rows'][0]['barcode'])->toBe('0850050062035')->and($result['rows'][1]['barcode'])->toBe('812256024194')
        ->and($result['rows'][0]['product_code'])->toBe('000045')->and($result['rows'][0]['price'])->toBe('2500.50')
        ->and($result['rows'][0]['cost_price'])->toBe('1500.25')->and($result['rows'][0]['stock'])->toBe(10)
        ->and(array_column($result['ignored_columns'], 'source_column'))->toContain('creado', 'costo inventario', 'precio inventario', 'color')
        ->and($result['rows'][0]['attributes'])->toBe([]);
})->with(['xlsx', 'xls']);

test('API uploads workbook and confirms only server session ignoring tampered client rows', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $this->actingAs($owner, 'sanctum');
    $preview = $this->post('/api/v1/shops/'.$shop->public_id.'/inventory-import/preview', ['file' => adaptiveWorkbook()])->assertOk();
    $preview->assertJsonPath('header_row', 8)->assertJsonPath('rows.0.barcode', '0850050062035')
        ->assertJsonStructure(['file', 'sheet', 'sheets', 'mapping_confidence', 'ignored_columns', 'warnings', 'sample_rows', 'counts']);
    $result = $this->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', ['session_id' => $preview->json('session_id'), 'create_missing_categories' => true, 'rows' => [['name' => 'Tampered', 'price' => '0']]])->assertOk();
    $result->assertJsonPath('summary.created', 2)->assertJsonPath('summary.categories_created', 1);
    expect($shop->products()->where('name', 'Tampered')->exists())->toBeFalse();
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', ['session_id' => $preview->json('session_id')])->assertOk()->assertJsonPath('summary.created', 2);
    expect($shop->products()->count())->toBe(2);
});

test('legacy raw-row retries skip the same inventory instead of duplicating products', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $this->actingAs($owner, 'sanctum');
    $rows = [[
        'line' => 2,
        'name' => 'Producto sin código',
        'price' => '2500.00',
        'stock' => 4,
        'valid' => true,
    ]];

    $first = $this->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', ['rows' => $rows])
        ->assertCreated()
        ->assertJsonPath('summary.created', 1)
        ->assertJsonPath('summary.skipped', 0);
    $second = $this->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', ['rows' => $rows])
        ->assertCreated()
        ->assertJsonPath('imported', 0)
        ->assertJsonPath('summary.created', 0)
        ->assertJsonPath('summary.skipped', 1);

    expect($first->json('imported'))->toBe(1)
        ->and($second->json('imported'))->toBe(0)
        ->and($shop->products()->where('name', 'Producto sin código')->count())->toBe(1);
});

test('legacy raw rows persist price and cost using canonical decimal strings', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $this->actingAs($owner, 'sanctum');

    $this->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', [
        'rows' => [[
            'line' => 2,
            'name' => 'Importación exacta',
            'price' => '0.29',
            'cost_price' => '0.10',
            'stock' => 3,
            'valid' => true,
        ]],
    ])->assertCreated()->assertJsonPath('summary.created', 1);

    $product = $shop->products()->where('name', 'Importación exacta')->firstOrFail();

    expect($product->price)->toBe('0.29')
        ->and($product->inventory->cost_price)->toBe('0.10')
        ->and($product->inventory->stock_quantity)->toBe(3);
});

test('manual layout remapping uses temporary upload without rereading a file and attributes are opt in', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $this->actingAs($owner, 'sanctum');
    $url = '/api/v1/shops/'.$shop->public_id.'/inventory-import/preview';
    $first = $this->post($url, ['file' => adaptiveWorkbook()])->assertOk();
    $this->postJson($url, ['upload_token' => $first->json('upload_token'), 'sheet_index' => 1, 'header_row' => 8, 'attribute_columns' => ['color']])
        ->assertOk()->assertJsonPath('rows.0.attributes.0.name', 'Color')->assertJsonPath('rows.0.attributes.0.value', 'Azul');
    $this->postJson($url, ['upload_token' => $first->json('upload_token'), 'sheet_index' => 19])->assertUnprocessable();
});

test('temporary uploads and import sessions are private to their creator', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $other = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $otherShop = Shop::factory()->for($other)->create();
    $service = app(InventoryImportService::class);
    $first = $service->preview(adaptiveWorkbook(), [], $shop, $owner);
    expect(fn () => $service->preview(null, [], $otherShop, $other, ['upload_token' => $first['upload_token']]))->toThrow(HttpException::class);
    $session = InventoryImportSession::where('public_id', $first['session_id'])->firstOrFail();
    expect(fn () => $service->confirmSession($shop, $session, [], app(PlanLimitsService::class), app(CatalogMediaService::class), $other))->toThrow(HttpException::class);
    $session->update(['expires_at' => now()->subMinute()]);
    expect(fn () => $service->confirmSession($shop, $session, [], app(PlanLimitsService::class), app(CatalogMediaService::class), $owner))->toThrow(ValidationException::class);
});

test('title empty rows and punctuation abbreviations do not hide the header', function () {
    $csv = "Inventario octubre\n\nCod. Prod.;Nombre;P. Costo;P. Venta;Cant.\nABC-001;Articulo;125,50;250,00;10,0\n\nDEF-002;Otro;120;250;10.5\n";
    $result = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('abbr.csv', $csv));
    expect($result['header_row'])->toBe(3)->and($result['rows'][0]['line'])->toBe(4)->and($result['rows'][1]['line'])->toBe(6)
        ->and($result['rows'][0]['product_code'])->toBe('ABC-001')->and($result['rows'][0]['stock'])->toBe(10)
        ->and($result['rows'][1]['valid'])->toBeFalse();
});

test('delimiter detection supports quoted multiline data and English headers', function (string $delimiter) {
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['Name', 'SKU', 'Unit price', 'Unit cost', 'Qty', 'Notes'], $delimiter, '"', '');
    fputcsv($handle, ['An item', '000045', '1250.75', '750.00', '10.0', "Multiline\nwith, comma; pipe|"], $delimiter, '"', '');
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    $result = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('quoted.csv', "\xEF\xBB\xBF".$csv));
    expect($result['valid_rows'])->toBe(1)->and($result['rows'][0]['product_code'])->toBe('000045')->and($result['rows'][0]['price'])->toBe('1250.75')->and($result['rows'][0]['notes'])->toContain("Multiline\n");
})->with([',', ';', "\t", '|']);

test('legacy encoding conversion preserves accented values and warns', function () {
    $csv = mb_convert_encoding("Nombre;Categoría;Precio\nCafé;Bebidas;250\n", 'Windows-1252', 'UTF-8');
    $result = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('cp1252.csv', $csv));
    expect($result['valid_rows'])->toBe(1)->and($result['rows'][0]['name'])->toBe('Café')->and($result['warnings'])->not->toBeEmpty();
});

test('unknown headers require a manual row and mapping instead of a misleading empty session', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $file = UploadedFile::fake()->createWithContent('custom.csv', "Mi titulo\nColumna A;Columna B;Extra\nProducto;250;Azul\n");
    $service = app(InventoryImportService::class);
    $result = $service->preview($file, [], $shop, $owner);
    expect($result['session_id'])->toBeNull()->and($result['needs_header_selection'])->toBeTrue();
    $manual = $service->preview(null, ['name' => 'columna a', 'price' => 'columna b'], $shop, $owner, ['upload_token' => $result['upload_token'], 'header_row' => 2, 'manual_mapping' => true]);
    expect($manual['valid_rows'])->toBe(1)->and($manual['session_id'])->not->toBeNull();
});

test('unknown fifteen column layouts do not become automatic attributes and mapping cannot reuse a column', function () {
    $headers = array_merge(['Nombre', 'Precio'], array_map(fn ($i) => 'Extra '.$i, range(1, 15)));
    $csv = implode(';', $headers)."\nProducto;250;".implode(';', array_fill(0, 15, 'valor'))."\n";
    $service = app(InventoryImportService::class);
    $result = $service->preview(UploadedFile::fake()->createWithContent('extra.csv', $csv));
    expect($result['valid_rows'])->toBe(1)->and($result['ignored_columns'])->toHaveCount(15)->and($result['rows'][0]['attributes'])->toBe([]);
    expect(fn () => $service->preview(UploadedFile::fake()->createWithContent('bad.csv', $csv), ['cost_price' => 'precio']))->toThrow(ValidationException::class);
});

test('fresh detection overrides stale saved mapping and explicit no import is honored', function () {
    $service = app(InventoryImportService::class);
    $csv = "Nombre;Precio;Costo\nProducto;250;100\n";
    $result = $service->preview(UploadedFile::fake()->createWithContent('new.csv', $csv), ['price' => 'costo', 'cost_price' => 'precio'], null, null, ['manual_mapping' => false]);
    expect($result['mapping']['price'])->toBe('precio')->and($result['mapping']['cost_price'])->toBe('costo');
    $manual = $service->preview(UploadedFile::fake()->createWithContent('new.csv', $csv), ['cost_price' => '']);
    expect($manual['rows'][0]['cost_price'])->toBeNull();
});

test('formatted numeric codes recover zeros without formula execution', function () {
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray(['Nombre', 'Barcode', 'SKU', 'Precio'], null, 'A1');
    $sheet->fromArray(['Producto', 850050062035, 45, '250.00'], null, 'A2');
    $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('0000000000000');
    $sheet->getStyle('C2')->getNumberFormat()->setFormatCode('000000');
    $path = tempnam(sys_get_temp_dir(), 'inventory-format-');
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();
    $result = app(InventoryImportService::class)->preview(new UploadedFile($path, 'zero.xlsx', null, null, true));
    expect($result['rows'][0]['barcode'])->toBe('0850050062035')->and($result['rows'][0]['product_code'])->toBe('000045');
});

test('money is exact with large values and stock never rounds fractional or negative quantities', function () {
    expect(InventoryImportService::parseMoney('1 250,75'))->toBe('1250.75')
        ->and(InventoryImportService::parseMoney('9999999999999999.99'))->toBe('9999999999999999.99')
        ->and(InventoryImportService::parseMoney('0.005'))->toBe('0.01');
    foreach (['10', '10.0', '10,0'] as $value) {
        expect(InventoryImportService::parseStock($value))->toBe(10);
    }
    foreach (['10.5', '10,5', '-1', 'no'] as $value) {
        expect(InventoryImportService::parseStock($value))->toBe(-1);
    }
    $result = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('bad.csv', "Nombre;Precio;Costo\nProducto;-10;xxx\n"));
    expect($result['rows'][0]['valid'])->toBeFalse()->and($result['rows'][0]['errors'])->toHaveCount(2);
});

test('safe file limits reject excessive columns rows and unsupported formats', function () {
    $reader = app(WorkbookReader::class);
    expect(fn () => $reader->read(UploadedFile::fake()->createWithContent('bad.csv', implode(';', array_fill(0, 101, 'name')))))->toThrow(ValidationException::class);
    expect(fn () => $reader->read(UploadedFile::fake()->createWithContent('bad.txt', str_repeat("Nombre;Precio\n", 5051))))->toThrow(ValidationException::class);
    expect(fn () => $reader->read(UploadedFile::fake()->createWithContent('bad.pdf', 'content')))->toThrow(ValidationException::class);
});

test('PDF catalogue imports selectable text rows with sale price and optional stock', function () {
    $dompdf = new Dompdf;
    $dompdf->loadHtml(<<<'HTML'
        <div>Nombre;Precio de venta;Stock;Imagen</div>
        <div>Perfume A;RD$ 2500.00;10;https://images.example.test/perfume-a.jpg</div>
        <div>Crema B;1250;5;</div>
    HTML);
    $dompdf->render();
    $path = tempnam(sys_get_temp_dir(), 'catalog-pdf-');
    file_put_contents($path, $dompdf->output());

    $result = app(InventoryImportService::class)->preview(new UploadedFile($path, 'catalogo.pdf', 'application/pdf', null, true));

    expect($result['file']['type'])->toBe('pdf')
        ->and($result['valid_rows'])->toBe(2)
        ->and($result['mapping']['name'])->toBe('nombre')
        ->and($result['mapping']['price'])->toBe('precio de venta')
        ->and($result['mapping']['image'])->toBe('imagen')
        ->and($result['rows'][0]['price'])->toBe('2500.00')
        ->and($result['rows'][0]['stock'])->toBe(10)
        ->and($result['rows'][0]['image'])->toBe('https://images.example.test/perfume-a.jpg');
});

test('catalogue image URL is kept in the preview and queued only after confirmation', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $service = app(InventoryImportService::class);
    $preview = $service->preview(
        UploadedFile::fake()->createWithContent('catalogo.csv', "Nombre;Precio de venta;Stock;Imagen\nProducto con foto;2500;4;https://images.example.test/producto.jpg\n"),
        [],
        $shop,
        $owner,
    );

    expect($preview['rows'][0]['image'])->toBe('https://images.example.test/producto.jpg');

    $summary = $service->confirmSession($shop, $preview['session'], ['duplicate_strategy' => 'create'], app(PlanLimitsService::class), app(CatalogMediaService::class), $owner);
    $product = $shop->products()->where('name', 'Producto con foto')->firstOrFail();

    expect($summary['created'])->toBe(1)->and($product->price)->toBe('2500.00');
    Queue::assertPushed(ProcessImportedProductImageJob::class, fn ($job): bool => $job->productId === $product->id && $job->url === 'https://images.example.test/producto.jpg');
});

test('embedded Excel product image follows its product row into the import session', function () {
    $imagePath = tempnam(sys_get_temp_dir(), 'catalog-image-').'.png';
    $image = imagecreatetruecolor(12, 12);
    imagepng($image, $imagePath);
    imagedestroy($image);

    $workbook = new Spreadsheet;
    $sheet = $workbook->getActiveSheet();
    $sheet->fromArray([
        ['Nombre', 'Precio de venta', 'Stock'],
        ['Producto embebido', '1800', '3'],
    ]);
    $drawing = new Drawing;
    $drawing->setPath($imagePath);
    $drawing->setCoordinates('A2');
    $drawing->setWorksheet($sheet);
    $xlsxPath = tempnam(sys_get_temp_dir(), 'catalog-xlsx-').'.xlsx';
    (new Xlsx($workbook))->save($xlsxPath);
    $workbook->disconnectWorksheets();

    $result = app(InventoryImportService::class)->preview(new UploadedFile($xlsxPath, 'catalogo.xlsx', null, null, true));

    expect($result['valid_rows'])->toBe(1)
        ->and($result['rows'][0]['embedded_image']['mime_type'])->toBe('image/png')
        ->and($result['rows'][0]['price'])->toBe('1800.00');
});

test('1500 row workbook preview has bounded query count and reports benchmark', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $file = adaptiveWorkbook('xlsx', 1500);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $started = microtime(true);
    $memory = memory_get_usage(true);
    $result = app(InventoryImportService::class)->preview($file, [], $shop, $owner);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    fwrite(STDERR, sprintf("\nIMPORT_BENCHMARK rows=1500 duration_ms=%d queries=%d memory_delta_mb=%.2f peak_mb=%.2f\n", (microtime(true) - $started) * 1000, $queries, (memory_get_usage(true) - $memory) / 1048576, memory_get_peak_usage(true) / 1048576));
    expect($result['valid_rows'])->toBe(1500)->and($queries)->toBeLessThan(20);
});

test('Unicode normalization and reliable mojibake aliases keep original labels and warn', function () {
    $csv = "\xEF\xBB\xBFNombre;CÃ³digo del producto;CategorÃ­a;Precio unitario;Cant.\nProducto;0850050062035;Perfumes;250;10\n";
    $result = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('mojibake.csv', $csv));
    expect($result['valid_rows'])->toBe(1)->and($result['mapping']['barcode'])->toBe('codigo del producto')
        ->and($result['original_headers'][1])->toBe('CÃ³digo del producto')->and($result['mojibake_warning'])->toBeTrue();
    expect(app(ColumnDetector::class)->normalize('Ｐ． Ｖｅｎｔａ'))->toBe('p venta');
});

test('duplicate header labels are independently selectable and manual no import makes columns ignored', function () {
    $csv = "Nombre;Precio;Precio\nProducto;250;100\n";
    $result = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('duplicates.csv', $csv), ['price' => 'column 2', 'cost_price' => 'column 3']);
    expect($result['rows'][0]['price'])->toBe('250.00')->and($result['rows'][0]['cost_price'])->toBe('100.00')->and($result['warnings'])->not->toBeEmpty();
    $ignored = app(InventoryImportService::class)->preview(UploadedFile::fake()->createWithContent('ignore.csv', "Nombre;Precio;Costo\nProducto;250;100\n"), ['cost_price' => '']);
    expect(array_column($ignored['ignored_columns'], 'source_column'))->toContain('costo');
});

test('barcode collisions at confirm do not consume quota or duplicate products from stale previews', function () {
    config()->set('catalog.plans.pro.max_products_per_shop', 2);
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $service = app(InventoryImportService::class);
    $a = $service->preview(adaptiveWorkbook(), [], $shop, $owner);
    $b = $service->preview(adaptiveWorkbook(), [], $shop, $owner);
    $first = InventoryImportSession::where('public_id', $a['session_id'])->firstOrFail();
    $stale = clone $first;
    $limits = app(PlanLimitsService::class);
    $media = app(CatalogMediaService::class);
    $service->confirmSession($shop, $first, [], $limits, $media, $owner);
    expect($service->confirmSession($shop, $stale, [], $limits, $media, $owner)['created'])->toBe(2);
    $other = InventoryImportSession::where('public_id', $b['session_id'])->firstOrFail();
    $summary = $service->confirmSession($shop, $other, ['duplicate_strategy' => 'create'], $limits, $media, $owner);
    expect($summary['created'])->toBe(0)->and($summary['skipped'])->toBe(2)->and($shop->products()->count())->toBe(2);
});

test('ZIP bombs invalid Excel signatures and expired temporary files fail safely', function () {
    $reader = app(WorkbookReader::class);
    expect(fn () => $reader->read(UploadedFile::fake()->createWithContent('fake.xls', '<html>Not Excel</html>')))->toThrow(ValidationException::class);
    $path = tempnam(sys_get_temp_dir(), 'inventory-bomb-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat('A', 2 * 1024 * 1024));
    $zip->close();
    expect(fn () => $reader->read(new UploadedFile($path, 'bomb.xlsx', null, null, true)))->toThrow(ValidationException::class);
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $service = app(InventoryImportService::class);
    $preview = $service->preview(adaptiveWorkbook(), [], $shop, $owner);
    $this->travel(3)->hours();
    expect(fn () => $service->preview(null, [], $shop, $owner, ['upload_token' => $preview['upload_token']]))->toThrow(ValidationException::class);
});

test('web can remap and choose a sheet without a second file upload', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $this->actingAs($owner);
    $url = route('seller.shops.products.import.preview', $shop);
    $first = $this->post($url, ['file' => adaptiveWorkbook()])->assertOk()->assertSee('Encabezados en fila 8')->assertSee('Costo unitario');
    $detection = $first->viewData('detection');
    $this->post($url, ['upload_token' => $detection['upload_token'], 'header_row' => 8, 'sheet_index' => 1, 'mapping' => $detection['mapping']])
        ->assertOk()->assertViewHas('validRows', 2)->assertSee('Producto anónimo 1');
});
