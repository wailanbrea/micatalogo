<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('No se ejecutan pruebas sin conexión SQLite en memoria verificada.');
}
echo json_encode(['environment' => $app->environment(), 'connection' => $connection->getName(),
    'driver' => $connection->getDriverName(), 'host' => 'none (in-memory)', 'database' => $connection->getDatabaseName()], JSON_UNESCAPED_SLASHES).PHP_EOL;
