<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$manifest = json_decode(file_get_contents(($argv[1] ?? '').'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
$checks = [];
foreach (['users', 'shops', 'products', 'orders', 'invoices'] as $table) {
    $ids = Illuminate\Support\Facades\DB::table($table)->orderBy('id')->pluck('id')->all();
    $ids = array_map('strval', $ids);
    $checks[$table] = [
        'before' => $manifest[$table]['count'], 'after' => count($ids),
        'added' => count(array_diff($ids, $manifest[$table]['ids'])),
        'removed' => count(array_diff($manifest[$table]['ids'], $ids)),
        'identical' => $manifest[$table]['ids_sha256'] === hash('sha256', json_encode($ids)),
    ];
}
// PDO MySQL returns IDs as integers on some runtimes; normalize both sides in backup.
echo json_encode($checks, JSON_PRETTY_PRINT);
exit(collect($checks)->contains(fn($check) => $check['removed'] > 0) ? 1 : 0);
