<?php

// CLI-only safety tool. No credentials or record contents are printed.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('production') || config('database.default') !== 'mysql') {
    throw new RuntimeException('Solo respaldo de producción MySQL; entorno no válido.');
}
$directory = $argv[1] ?? '';
if ($directory === '' || ! is_dir($directory)) { throw new RuntimeException('Falta carpeta de respaldo existente.'); }
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->beginTransaction();
$manifest = [];
foreach (['users', 'shops', 'products', 'orders', 'invoices'] as $table) {
    $ids = $pdo->query("SELECT id FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_map('strval', $ids);
    $manifest[$table] = ['count' => count($ids), 'ids' => $ids, 'ids_sha256' => hash('sha256', json_encode($ids))];
}
$filename = $directory.'/database.sql';
if (file_exists($filename)) { throw new RuntimeException('No se sobrescribe un respaldo existente.'); }
$handle = fopen($filename, 'xb');
if (! $handle) { throw new RuntimeException('No se pudo crear respaldo.'); }
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
try {
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $backupRows = [];
    foreach ($tables as $table) {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) { throw new RuntimeException('Nombre de tabla inválido.'); }
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($handle, $create.";\n");
        $statement = $pdo->query("SELECT * FROM `$table`");
        $rows = 0;
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $columns = implode(',', array_map(fn($key) => '`'.$key.'`', array_keys($row)));
            $values = implode(',', array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row)));
            if (fwrite($handle, "INSERT INTO `$table` ($columns) VALUES ($values);\n") === false) {
                throw new RuntimeException('Escritura de respaldo falló.');
            }
            $rows++;
        }
        $backupRows[$table] = $rows;
    }
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    fclose($handle);
    throw $error;
}
fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n-- BACKUP COMPLETE\n");
fclose($handle);
$manifest['backup'] = ['bytes' => filesize($filename), 'sha256' => hash_file('sha256', $filename), 'rows' => $backupRows];
file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
if ($manifest['backup']['bytes'] <= 100 || ! str_ends_with(file_get_contents($filename), "-- BACKUP COMPLETE\n")) {
    throw new RuntimeException('Respaldo incompleto.');
}
echo json_encode(['verified_file' => true, 'sha256' => $manifest['backup']['sha256'], 'counts' => array_map(fn($table) => $manifest[$table]['count'], ['users', 'shops', 'products', 'orders', 'invoices'])], JSON_PRETTY_PRINT);
