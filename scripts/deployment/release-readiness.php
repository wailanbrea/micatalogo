<?php

use App\Services\InventoryImport\ColumnDetector;
use App\Services\InventoryImport\WorkbookReader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only release check. Never creates fixtures or mutates application data.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = $argv[1] ?? dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$snapshot = [];
foreach (['users', 'shops', 'products', 'orders', 'invoices'] as $table) {
    if (Schema::hasTable($table)) {
        $ids = DB::table($table)->orderBy('id')->pluck('id')->all();
        $snapshot[$table] = ['count' => count($ids), 'identifiers_sha256' => hash('sha256', json_encode($ids))];
    }
}
$result = ['data' => $snapshot, 'cache_driver' => config('cache.default'),
    'release' => config('android-release'), 'bcmath' => extension_loaded('bcmath'),
    'reader_available' => class_exists(WorkbookReader::class)];
if (isset($argv[2])) {
    $file = new UploadedFile($argv[2], 'inventory-import-row8.xlsx', null, null, true);
    $book = $app->make(WorkbookReader::class)->read($file);
    $sheet = $book['sheets'][1];
    $detector = $app->make(ColumnDetector::class);
    $header = $detector->header($sheet['rows']);
    $mapping = $detector->mapping($header['values'], array_slice($sheet['rows'], $header['row']));
    $result['workbook_smoke'] = ['type' => $book['type'], 'sheets' => count($book['sheets']),
        'header_row' => $header['row'], 'mapping' => $mapping, 'first_row' => $sheet['rows'][$header['row']]];
    if ($header['row'] !== 8) {
        throw new RuntimeException('Unexpected header detection.');
    }
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
