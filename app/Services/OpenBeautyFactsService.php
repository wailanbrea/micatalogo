<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OpenBeautyFactsService
{
    /**
     * @return array{status: string, payload?: array<string, mixed>, product?: array<string, mixed>}
     */
    public function lookup(string $barcode): array
    {
        $barcode = trim($barcode);
        $cacheKey = 'catalog:open-beauty-facts:'.$barcode;

        return Cache::remember($cacheKey, now()->addSeconds((int) config('catalog.open_beauty_facts.cache_seconds', 86400)), function () use ($barcode): array {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout((int) config('catalog.open_beauty_facts.connect_timeout', 5))
                    ->timeout((int) config('catalog.open_beauty_facts.timeout', 10))
                    ->retry(2, 150)
                    ->get(rtrim(config('catalog.open_beauty_facts.endpoint'), '/').'/'.rawurlencode($barcode).'.json');
            } catch (ConnectionException $exception) {
                throw new RuntimeException('Open Beauty Facts no está disponible temporalmente.', 0, $exception);
            }

            if ($response->status() === 404) {
                return ['status' => 'not_found'];
            }

            if (! $response->successful()) {
                throw new RuntimeException('Open Beauty Facts respondió con HTTP '.$response->status().'.');
            }

            if ((int) $response->json('status', 0) !== 1) {
                return ['status' => 'not_found'];
            }

            $payload = $response->json();
            if (! is_array($payload) || ! is_array($payload['product'] ?? null)) {
                return ['status' => 'not_found'];
            }

            return [
                'status' => 'found',
                'payload' => $payload,
                'product' => $payload['product'],
            ];
        });
    }

    public function downloadImage(string $url): string
    {
        $parsed = parse_url($url);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $allowedHosts = config('catalog.open_beauty_facts.allowed_image_hosts', []);

        if (($parsed['scheme'] ?? '') !== 'https' || ! in_array($host, $allowedHosts, true)) {
            throw new RuntimeException('La imagen externa no pertenece a un dominio permitido.');
        }

        try {
            $response = Http::accept('image/*')
                ->connectTimeout((int) config('catalog.open_beauty_facts.connect_timeout', 5))
                ->timeout((int) config('catalog.open_beauty_facts.image_timeout', 20))
                ->retry(2, 150)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo descargar la imagen de Open Beauty Facts.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('La imagen externa respondió con HTTP '.$response->status().'.');
        }

        $bytes = $response->body();
        $maxBytes = (int) config('catalog.open_beauty_facts.max_image_bytes', 10 * 1024 * 1024);
        if (strlen($bytes) > $maxBytes || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('La imagen externa excede el límite o no es válida.');
        }

        return $bytes;
    }

    public function imageUrl(array $product): ?string
    {
        foreach (['image_front_url', 'image_front_small_url', 'image_front_thumb_url'] as $key) {
            $url = $product[$key] ?? null;
            if (is_string($url) && Str::startsWith($url, 'https://')) {
                return $url;
            }
        }

        return null;
    }

    public function normalizeBarcode(?string $barcode): ?string
    {
        if ($barcode === null) {
            return null;
        }

        $barcode = preg_replace('/[^0-9]/', '', $barcode) ?? '';

        return $barcode === '' ? null : $barcode;
    }
}
