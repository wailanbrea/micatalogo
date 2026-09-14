<?php

use App\Models\Product;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
