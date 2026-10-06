<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WebImageSearchService
{
    public function search(string $query): array
    {
        $query = trim(Str::limit($query, 120, ''));

        if (mb_strlen($query) < 2) {
            return [];
        }

        return Cache::remember(
            'catalog.web-image-search.'.hash('sha256', mb_strtolower($query)),
            now()->addMinutes((int) config('catalog.web_image_search.cache_minutes', 10)),
            function () use ($query): array {
                $response = Http::acceptJson()
                    ->timeout((int) config('catalog.web_image_search.timeout', 8))
                    ->get(config('catalog.web_image_search.endpoint'), [
                        'q' => $query,
                        'page_size' => (int) config('catalog.web_image_search.page_size', 12),
                        'mature' => 'false',
                    ]);

                if ($response->failed()) {
                    return [];
                }

                return collect($response->json('results', []))
                    ->map(function (array $result): ?array {
                        $imageUrl = $result['url'] ?? null;
                        $thumbnailUrl = $result['thumbnail'] ?? $imageUrl;

                        if (! is_string($imageUrl) || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                            return null;
                        }

                        return [
                            'id' => (string) ($result['id'] ?? hash('sha256', $imageUrl)),
                            'title' => Str::limit(trim((string) ($result['title'] ?? 'Imagen')), 100, ''),
                            'thumbnail' => is_string($thumbnailUrl) && filter_var($thumbnailUrl, FILTER_VALIDATE_URL) ? $thumbnailUrl : $imageUrl,
                            'url' => $imageUrl,
                            'creator' => Str::limit(trim((string) ($result['creator'] ?? '')), 80, ''),
                            'license' => Str::limit(trim((string) ($result['license'] ?? '')), 80, ''),
                            'source' => Str::limit(trim((string) ($result['source'] ?? 'Openverse')), 80, ''),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }
        );
    }

    public function assertSafeImageUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || $host === '') {
            throw new InvalidArgumentException('La imagen debe provenir de una URL HTTPS válida.');
        }

        $allowedHosts = config('catalog.web_image_search.allowed_hosts', []);
        $allowed = collect($allowedHosts)->contains(fn (string $allowedHost): bool =>
            $host === $allowedHost || Str::endsWith($host, '.'.$allowedHost)
        );

        if (! $allowed) {
            throw new InvalidArgumentException('La fuente de imagen no está permitida. Vuelve a buscar y selecciona un resultado válido.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new InvalidArgumentException('La fuente de imagen no es válida.');
        }
    }
}
