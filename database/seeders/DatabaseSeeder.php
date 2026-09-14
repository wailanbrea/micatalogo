<?php

namespace Database\Seeders;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categoryNames = ['Software', 'Tecnologia', 'Moda', 'Hogar', 'Belleza', 'Accesorios', 'Calzado', 'Deportes'];
        $categories = collect($categoryNames)->map(function (string $name, int $index) {
            return GlobalCategory::firstOrCreate(
                ['slug' => str($name)->slug()],
                ['name' => $name, 'status' => 'active', 'sort_order' => $index]
            );
        })->keyBy('slug');

        // Platform Owner / Administrator
        $ownerUser = User::firstOrCreate(
            ['email' => 'wailandkey@gmail.com'],
            [
                'name' => 'Wailan Brea',
                'password' => 'Owner2026!#',
                'email_verified_at' => now(),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        // Official BSolutions.dev Catalog (Owned by Wailan Brea)
        $bsolutionsShop = Shop::firstOrCreate(
            ['slug' => 'bsolutions-dev'],
            [
                'user_id' => $ownerUser->id,
                'name' => 'BSolutions.dev',
                'description' => 'Desarrollo de software profesional, soluciones SaaS, CRM para WhatsApp Business API y plataformas web de alto rendimiento.',
                'whatsapp_country_code' => '1',
                'whatsapp_number' => '8095550100',
                'instagram' => 'bsolutions.dev',
                'offers_shipping' => false,
                'status' => 'active',
            ]
        );

        $softwareShopCat = ShopCategory::firstOrCreate(
            ['shop_id' => $bsolutionsShop->id, 'slug' => 'software-saas'],
            ['name' => 'Software & SaaS', 'sort_order' => 0, 'status' => 'active']
        );

        $softwareCategory = $categories->get('software');
        $assetBaseUrl = rtrim((string) config('app.url'), '/');

        $bsolutionsProducts = [
            [
                'name' => 'CRM WhatsApp Multiagente',
                'slug' => 'bsolutionscrmwas',
                'price' => 9500,
                'cost_price' => 3800,
                'stock' => 12,
                'sold' => 8,
                'low_stock' => 3,
                'description' => "Bandeja multiagente para WhatsApp Business API.\nIncluye asignación de conversaciones, respuestas rápidas, etiquetas, métricas de atención y seguimiento por cliente.",
                'image' => "{$assetBaseUrl}/images/catalog/crm-whatsapp.svg",
            ],
            [
                'name' => 'TicketPro Banca',
                'slug' => 'ticketpro',
                'price' => 18000,
                'cost_price' => 7500,
                'stock' => 2,
                'sold' => 14,
                'low_stock' => 3,
                'description' => "Sistema para bancas de lotería con ventas, reportes, control de límites, auditoría y administración por sucursal.\nPensado para operaciones que necesitan control diario y trazabilidad.",
                'image' => "{$assetBaseUrl}/images/catalog/ticketpro.svg",
            ],
            [
                'name' => 'MiCatalogo Pro',
                'slug' => 'micatalogo-pro',
                'price' => 3500,
                'cost_price' => 1200,
                'stock' => 25,
                'sold' => 32,
                'low_stock' => 5,
                'description' => "Catálogo digital para negocios que venden por WhatsApp.\nIncluye página pública, productos con fotos, botón directo a WhatsApp, enlaces compartibles y panel para administrar tiendas.",
                'image' => "{$assetBaseUrl}/images/catalog/micatalogo-pro.svg",
            ],
            [
                'name' => 'Bot Automatizado WhatsApp',
                'slug' => 'bot-automatizado-whatsapp',
                'price' => 7500,
                'cost_price' => 2800,
                'stock' => 1,
                'sold' => 9,
                'low_stock' => 2,
                'description' => "Bot para responder preguntas frecuentes, enviar catálogo, capturar datos del cliente y derivar conversaciones a un asesor.\nIdeal para negocios que reciben mensajes fuera de horario.",
                'image' => "{$assetBaseUrl}/images/catalog/bot-whatsapp.svg",
            ],
        ];

        foreach ($bsolutionsProducts as $pData) {
            $prod = Product::updateOrCreate(
                ['shop_id' => $bsolutionsShop->id, 'slug' => $pData['slug']],
                [
                    'name' => $pData['name'],
                    'description' => $pData['description'],
                    'price' => $pData['price'],
                    'currency' => 'DOP',
                    'availability_status' => ProductAvailabilityStatus::Available,
                    'moderation_status' => ProductModerationStatus::Active,
                    'global_category_id' => $softwareCategory->id,
                    'shop_category_id' => $softwareShopCat->id,
                    'published_at' => now(),
                ]
            );

            ProductInventory::updateOrCreate(
                ['product_id' => $prod->id],
                [
                    'track_inventory' => true,
                    'cost_price' => $pData['cost_price'],
                    'stock_quantity' => $pData['stock'],
                    'sold_quantity' => $pData['sold'],
                    'low_stock_threshold' => $pData['low_stock'],
                ]
            );

            ProductImage::updateOrCreate(
                ['product_id' => $prod->id, 'sort_order' => 0],
                [
                    'object_key' => $pData['image'],
                    'thumbnail_object_key' => $pData['image'],
                    'mime_type' => 'image/webp',
                    'width' => 800,
                    'height' => 800,
                    'size_bytes' => 95000,
                    'checksum_sha256' => hash('sha256', $pData['image']),
                    'processing_status' => ProductImageProcessingStatus::Ready,
                ]
            );
        }

        // Demo Catalogs with realistic WebP images
        $catalogs = [
            [
                'name' => 'Brea Fashion',
                'slug' => 'brea-fashion',
                'phone' => '8095550188',
                'cat' => 'moda',
                'items' => [
                    ['Zapatillas urbanas', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80&fm=webp', 3200],
                    ['Camisa lino clasica', 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=800&auto=format&fit=crop&q=80&fm=webp', 1850],
                    ['Bolso de mano', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80&fm=webp', 2400],
                ],
            ],
            [
                'name' => 'Casa Nativa',
                'slug' => 'casa-nativa',
                'phone' => '8095550192',
                'cat' => 'hogar',
                'items' => [
                    ['Lampara de mesa', 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=800&auto=format&fit=crop&q=80&fm=webp', 1750],
                    ['Set de organizadores', 'https://images.unsplash.com/photo-1584589167171-541ce45f1eea?w=800&auto=format&fit=crop&q=80&fm=webp', 950],
                    ['Jarron artesanal', 'https://images.unsplash.com/photo-1612196808214-b8e1d6145a8c?w=800&auto=format&fit=crop&q=80&fm=webp', 1400],
                ],
            ],
            [
                'name' => 'Movil Pro',
                'slug' => 'movil-pro',
                'phone' => '8095550174',
                'cat' => 'tecnologia',
                'items' => [
                    ['Audifonos inalambricos', 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&auto=format&fit=crop&q=80&fm=webp', 2100],
                    ['Soporte para telefono', 'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=800&auto=format&fit=crop&q=80&fm=webp', 650],
                    ['Teclado compacto', 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=800&auto=format&fit=crop&q=80&fm=webp', 2800],
                ],
            ],
        ];

        foreach ($catalogs as $index => $catalog) {
            $user = User::firstOrCreate(
                ['email' => "demo{$index}@micatalogo.local"],
                ['name' => "Vendedor {$catalog['name']}", 'password' => 'password', 'email_verified_at' => now()]
            );

            $shop = Shop::firstOrCreate(
                ['slug' => $catalog['slug']],
                [
                    'user_id' => $user->id,
                    'name' => $catalog['name'],
                    'description' => "Productos seleccionados de {$catalog['name']}.",
                    'whatsapp_country_code' => '1',
                    'whatsapp_number' => $catalog['phone'],
                    'offers_shipping' => $index !== 1,
                    'status' => 'active',
                ]
            );
            $shop->update(['offers_shipping' => $index !== 1]);

            $catModel = $categories->get($catalog['cat']) ?? $categories->first();

            foreach ($catalog['items'] as [$item, $imgUrl, $price]) {
                $product = Product::firstOrCreate(
                    ['shop_id' => $shop->id, 'slug' => str($item)->slug()],
                    [
                        'name' => $item,
                        'description' => "{$item} disponible en {$catalog['name']}.",
                        'price' => $price,
                        'currency' => 'DOP',
                        'availability_status' => ProductAvailabilityStatus::Available,
                        'moderation_status' => ProductModerationStatus::Active,
                        'global_category_id' => $catModel->id,
                        'published_at' => now(),
                    ]
                );

                ProductImage::firstOrCreate(
                    ['product_id' => $product->id, 'sort_order' => 0],
                    [
                        'object_key' => $imgUrl,
                        'thumbnail_object_key' => $imgUrl,
                        'mime_type' => 'image/webp',
                        'width' => 800,
                        'height' => 800,
                        'size_bytes' => 85000,
                        'checksum_sha256' => hash('sha256', $imgUrl),
                        'processing_status' => ProductImageProcessingStatus::Ready,
                    ]
                );
            }
        }
    }
}
