<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\CatalogMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveProductCatalogMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public int $productId) {}

    public function handle(CatalogMediaService $mediaService): void
    {
        $product = Product::find($this->productId);
        if (! $product || ! $product->barcode) {
            return;
        }

        $mediaService->resolveProduct($product);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Falló el job de resolución de catálogo externo.', [
            'product_id' => $this->productId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
