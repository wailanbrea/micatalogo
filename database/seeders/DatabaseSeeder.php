<?php

namespace Database\Seeders;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\ProductImage;
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

        $bsolutionsProducts = [
            [
                'name' => 'BsolutionsCRMWAS',
                'slug' => 'bsolutionscrmwas',
                'price' => 4500,
                'description' => "CRM Multiagente para WhatsApp Business API.\nTenancy isolation, mensajería masiva oficial, automatización de respuestas y analítica en tiempo real.",
                'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&auto=format&fit=crop&q=80&fm=webp',
            ],
            [
                'name' => 'TicketPro',
                'slug' => 'ticketpro',
                'price' => 8500,
                'description' => "Ecosistema integral para gestión y auditoría de bancas de lotería.\nControl de límites por grupo y globales, sorteos en vivo y aplicación móvil Android.",
                'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop&q=80&fm=webp',
            ],
            [
                'name' => 'MiCatalogo Pro',
                'slug' => 'micatalogo-pro',
                'price' => 2500,
                'description' => "Vitrina digital y catálogo interactivo para ventas por WhatsApp.\nPermite a comercios publicar productos, compartir enlaces directos y recibir consultas sin comisiones.",
                'image' => 'https://images.unsplash.com/photo-1556742049-0a67c5574f73?w=800&auto=format&fit=crop&q=80&fm=webp',
            ],
            [
                'name' => 'Bot Automatizado WhatsApp',
                'slug' => 'bot-automatizado-whatsapp',
                'price' => 3500,
                'description' => "Chatbot con IA y reglas preconfiguradas para soporte y ventas 24/7.\nRespuestas automáticas, envío dinámico de catálogo y derivación fluida a agentes humanos.",
                'image' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80&fm=webp',
            ],
        ];

        foreach ($bsolutionsProducts as $pData) {
            $prod = Product::firstOrCreate(
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

            ProductImage::firstOrCreate(
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
