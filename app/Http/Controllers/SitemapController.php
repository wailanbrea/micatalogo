<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $shops = Shop::query()->where('status', 'active')->latest()->get();
        $products = Product::query()->public()->with('shop')->latest()->limit(500)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Home
        $xml .= '<url>';
        $xml .= '<loc>'.route('home').'</loc>';
        $xml .= '<changefreq>daily</changefreq>';
        $xml .= '<priority>1.0</priority>';
        $xml .= '</url>';

        // Active Shops
        foreach ($shops as $shop) {
            $xml .= '<url>';
            $xml .= '<loc>'.route('shops.show', $shop).'</loc>';
            $xml .= '<lastmod>'.$shop->updated_at->toAtomString().'</lastmod>';
            $xml .= '<changefreq>daily</changefreq>';
            $xml .= '<priority>0.7</priority>';
            $xml .= '</url>';
        }

        // Active Products
        foreach ($products as $prod) {
            $xml .= '<url>';
            $xml .= '<loc>'.route('products.show', [$prod->shop, $prod]).'</loc>';
            $xml .= '<lastmod>'.$prod->updated_at->toAtomString().'</lastmod>';
            $xml .= '<changefreq>daily</changefreq>';
            $xml .= '<priority>0.6</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
