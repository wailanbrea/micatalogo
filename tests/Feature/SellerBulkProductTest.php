<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Jobs\ProcessProductImageJob;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerBulkProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_seller_can_access_bulk_upload_screen(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $response = $this->actingAs($seller)->get("/panel/tiendas/{$shop->public_id}/subida-masiva");

        $response->assertOk();
        $response->assertSee('Subida Masiva de Productos');
        $response->assertSee('100 cupos libres de 100');
    }

    public function test_a_seller_can_batch_upload_products_without_images(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $category = ShopCategory::factory()->create(['shop_id' => $shop->id, 'name' => 'Ropa']);

        $payload = [
            'products' => [
                [
                    'name' => 'Camisa Oxford Azul',
                    'price' => '1800',
                    'shop_category_id' => $category->id,
                    'description' => 'Camisa 100% algodón',
                ],
                [
                    'name' => 'Pantalón Chino Beige',
                    'price' => '2400',
                    'shop_category_id' => $category->id,
                    'description' => 'Corte regular fit',
                ],
            ],
        ];

        $response = $this->actingAs($seller)
            ->post("/panel/tiendas/{$shop->public_id}/subida-masiva", $payload);

        $response->assertRedirect("/panel/tiendas/{$shop->public_id}/productos");
        $response->assertSessionHas('status', 'Se publicaron 2 productos exitosamente.');

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Camisa Oxford Azul',
            'slug' => 'camisa-oxford-azul',
            'price' => '1800.00',
            'currency' => 'DOP',
            'availability_status' => ProductAvailabilityStatus::Available->value,
            'moderation_status' => ProductModerationStatus::Active->value,
        ]);

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Pantalón Chino Beige',
            'slug' => 'pantalon-chino-beige',
            'price' => '2400.00',
            'currency' => 'DOP',
        ]);
    }

    public function test_a_seller_can_batch_upload_products_with_images_and_jobs_are_dispatched(): void
    {
        Queue::fake();
        Storage::fake('temp');

        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $file1 = UploadedFile::fake()->image('polo.jpg', 600, 600);
        $file2 = UploadedFile::fake()->image('short.png', 800, 800);

        $payload = [
            'products' => [
                [
                    'name' => 'Polo Deportivo',
                    'price' => '1200',
                    'image' => $file1,
                ],
                [
                    'name' => 'Short Running',
                    'price' => '950',
                    'image' => $file2,
                ],
            ],
        ];

        $response = $this->actingAs($seller)
            ->post("/panel/tiendas/{$shop->public_id}/subida-masiva", $payload);

        $response->assertRedirect("/panel/tiendas/{$shop->public_id}/productos");

        $polo = Product::where('slug', 'polo-deportivo')->firstOrFail();
        $short = Product::where('slug', 'short-running')->firstOrFail();

        $this->assertDatabaseHas('product_images', [
            'product_id' => $polo->id,
            'processing_status' => ProductImageProcessingStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $short->id,
            'processing_status' => ProductImageProcessingStatus::Pending->value,
        ]);

        Queue::assertPushed(ProcessProductImageJob::class, 2);
    }

    public function test_bulk_upload_enforces_free_product_limit(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        // Create 99 existing products
        Product::factory()->count(99)->create(['shop_id' => $shop->id]);

        // Attempt to upload 2 products (would reach 101, exceeding max 100)
        $payload = [
            'products' => [
                ['name' => 'Producto Excedente 1', 'price' => '500'],
                ['name' => 'Producto Excedente 2', 'price' => '600'],
            ],
        ];

        $response = $this->actingAs($seller)
            ->post("/panel/tiendas/{$shop->public_id}/subida-masiva", $payload);

        $response->assertSessionHasErrors('products');
        $this->assertEquals(99, $shop->products()->count());
    }

    public function test_a_seller_cannot_bulk_upload_to_another_sellers_shop(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $otherShop = Shop::factory()->create(['user_id' => $otherSeller->id]);

        $response = $this->actingAs($seller)->get("/panel/tiendas/{$otherShop->public_id}/subida-masiva");
        $response->assertForbidden();

        $response = $this->actingAs($seller)->post("/panel/tiendas/{$otherShop->public_id}/subida-masiva", [
            'products' => [
                ['name' => 'Hack Product', 'price' => '100'],
            ],
        ]);
        $response->assertForbidden();
    }
}
