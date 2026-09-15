<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;

class QrCodeSvgService
{
    public function generateSvg(string $content, int $size = 240): string
    {
        $cacheKey = 'qr-svg:'.hash('sha256', $size.'|'.$content);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($content, $size): string {
            if (class_exists(Writer::class) && class_exists(SvgImageBackEnd::class)) {
                $renderer = new ImageRenderer(
                    new RendererStyle($size, 1),
                    new SvgImageBackEnd
                );

                $writer = new Writer($renderer);
                $svg = $writer->writeString($content);

                // Hacer el SVG responsive reemplazando width y height fijos por 100% y agregando clases utilitarias
                return preg_replace(
                    '/<svg([^>]+)width="\d+" height="\d+"/',
                    '<svg$1width="100%" height="100%" class="w-full h-full object-contain"',
                    $svg
                );
            }

            // Fallback SVG si la librería no estuviera disponible
            return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $size $size" width="100%" height="100%" class="w-full h-full object-contain">
    <rect width="100%" height="100%" fill="#ffffff" />
    <rect x="20" y="20" width="50" height="50" fill="#0f172a" />
    <rect x="30" y="30" width="30" height="30" fill="#ffffff" />
    <rect x="37" y="37" width="16" height="16" fill="#0f172a" />
    <rect x="170" y="20" width="50" height="50" fill="#0f172a" />
    <rect x="180" y="30" width="30" height="30" fill="#ffffff" />
    <rect x="187" y="37" width="16" height="16" fill="#0f172a" />
    <rect x="20" y="170" width="50" height="50" fill="#0f172a" />
    <rect x="30" y="180" width="30" height="30" fill="#ffffff" />
    <rect x="37" y="187" width="16" height="16" fill="#0f172a" />
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="12" fill="#64748b" font-family="sans-serif">QR Code</text>
</svg>
SVG;
        });
    }
}
