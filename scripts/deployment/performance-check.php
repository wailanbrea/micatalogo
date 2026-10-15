<?php

// CLI-only, read-only probe: no SQL, credentials or user records are emitted.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$started = hrtime(true);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$bootstrapMs = (hrtime(true) - $started) / 1e6;
$count = 0;
$queryMs = 0;
Illuminate\Support\Facades\DB::listen(function ($query) use (&$count, &$queryMs) {
    $count++;
    $queryMs += $query->time;
});
$samples = [];
foreach (['/login', '/login', '/login'] as $path) {
    $start = hrtime(true);
    $before = $count;
    $beforeMs = $queryMs;
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle(Illuminate\Http\Request::create('https://micatalogo.bsolutions.dev'.$path));
    $samples[] = ['path' => $path, 'status' => $response->getStatusCode(), 'total_ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => $count - $before, 'query_ms' => round($queryMs - $beforeMs, 2)];
}
echo json_encode(['bootstrap_ms' => round($bootstrapMs, 2), 'samples' => $samples, 'config_cached' => $app->configurationIsCached(), 'routes_cached' => $app->routesAreCached(), 'opcache_cli' => ini_get('opcache.enable_cli')], JSON_PRETTY_PRINT).PHP_EOL;
