<?php

use App\Models\Product;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test {--filter= : Only run tests matching this expression}', function () {
    $command = [PHP_BINARY, base_path('vendor/bin/pest')];
    if ($filter = $this->option('filter')) {
        $command[] = '--filter';
        $command[] = $filter;
    }

    $process = new Process($command, base_path(), [
        'APP_ENV' => 'testing',
        'R2_ACCESS_KEY_ID' => '',
        'R2_SECRET_ACCESS_KEY' => '',
        'R2_BUCKET' => '',
        'R2_ENDPOINT' => '',
    ]);
    $process->setTimeout(null);
    $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

    return $process->getExitCode() ?? 1;
})->purpose('Run the Pest test suite');

Schedule::command('queue:prune-failed --hours=168')->weekly();

Schedule::call(function () {
    // 1. Clean local temporary upload storage older than 24 hours
    $tempDisk = Storage::disk('temp');
    $files = $tempDisk->allFiles();
    $threshold = now()->subHours(24)->timestamp;

    foreach ($files as $file) {
        if ($file === '.gitignore') {
            continue;
        }
        if ($tempDisk->lastModified($file) < $threshold) {
            $tempDisk->delete($file);
        }
    }

    // 2. Hard purge soft-deleted products older than retention limit (30 days)
    $retentionDays = (int) config('catalog.media.soft_delete_retention_days', 30);
    $expiredProducts = Product::onlyTrashed()
        ->where('deleted_at', '<', now()->subDays($retentionDays))
        ->get();

    foreach ($expiredProducts as $expired) {
        $expired->forceDelete();
    }
})->daily()->name('catalog:daily-maintenance');
