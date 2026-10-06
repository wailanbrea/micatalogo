<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ImageProcessingService;
use App\Services\WebImageSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImportedProductImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public int $productId,
        public ?string $bytes = null,
        public ?string $mimeType = null,
        public ?string $url = null,
    ) {}

    public function handle(ImageProcessingService $images, WebImageSearchService $webImages): void
    {
        $product = Product::find($this->productId);
        if (! $product) {
            return;
        }

        try {
            if (is_string($this->bytes) && $this->bytes !== '') {
                $images->storeRemoteAndDispatch($product, $this->bytes, (string) $this->mimeType, 'import');

                return;
            }

            if (! is_string($this->url) || $this->url === '') {
                return;
            }

            $webImages->assertSafeImageUrl($this->url);
            $response = Http::timeout((int) config('catalog.web_image_search.timeout', 8))
                ->withOptions(['allow_redirects' => false])
                ->get($this->url);
            if (! $response->successful()) {
                Log::warning('La imagen de catálogo no pudo descargarse', ['product_id' => $product->id, 'status' => $response->status()]);

                return;
            }

            $images->storeRemoteAndDispatch($product, $response->body(), (string) $response->header('Content-Type'), 'import');
        } catch (Throwable $exception) {
            // An image must never roll back a successful product/price import.
            Log::warning('La imagen de catálogo fue omitida', [
                'product_id' => $product->id,
                'url_host' => $this->url ? parse_url($this->url, PHP_URL_HOST) : null,
                'exception_type' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
