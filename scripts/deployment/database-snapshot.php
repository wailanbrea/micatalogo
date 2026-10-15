<?php

// Deployment safety: export only this application's database, never public files.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('This snapshot helper requires the application MariaDB connection.');
}
$config = $connection->getConfig();
$directory = storage_path('app/private/deployment-backups');
if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Could not create private snapshot directory.');
}
$path = $directory.'/release-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
$process = proc_open([
    'C:/xampp/mysql/bin/mysqldump.exe', '--single-transaction', '--quick', '--skip-lock-tables', '--hex-blob',
    '--host='.($config['host'] ?? '127.0.0.1'), '--port='.($config['port'] ?? 3306),
    '--user='.$config['username'], $config['database'],
], [0 => ['pipe', 'r'], 1 => ['file', $path, 'wb'], 2 => ['pipe', 'w']], $pipes, null,
    array_merge(getenv(), ['MYSQL_PWD' => (string) ($config['password'] ?? '')]), ['bypass_shell' => true]);
if (! is_resource($process)) {
    throw new RuntimeException('Could not start database export.');
}
fclose($pipes[0]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exit = proc_close($process);
if ($exit !== 0 || filesize($path) < 100) {
    // Do not emit raw diagnostics: a connection error may contain private configuration.
    throw new RuntimeException('Database export failed; snapshot is not approved. Exit '.$exit);
}
$handle = fopen($path, 'rb');
fseek($handle, max(0, filesize($path) - 4096));
$tail = stream_get_contents($handle);
fclose($handle);
if (! str_contains($tail, '-- Dump completed on')) {
    throw new RuntimeException('Database snapshot lacks its completion marker.');
}
echo json_encode(['status' => 'verified_export', 'path' => $path, 'bytes' => filesize($path),
    'sha256' => hash_file('sha256', $path)], JSON_PRETTY_PRINT).PHP_EOL;
