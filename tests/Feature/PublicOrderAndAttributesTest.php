<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicOrderAndAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_order_persists_snapshots_and_returns_whatsapp_message_url(): void
    {
        $shop = Shop::factory()->create(['slug' => 'perfumes-brea']);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Hawas Ice',
            'price' => 3300,
            'sale_price' => 2800,
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
        ]);

        $response = $this->postJson(route('orders.store', $shop), [
            'items' => [['id' => $product->public_id, 'quantity' => 2]],
            'customer_name' => 'Ana Pérez',
            'delivery_type' => 'delivery',
            'notes' => 'Av. Independencia #12',
        ]);

        $response->assertCreated()
            ->assertJsonPath('order_number', fn (string $value) => str_starts_with($value, 'MC-'))
            ->assertJsonPath('whatsapp_url', fn (string $value) => str_contains($value, 'wa.me/'));

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shop->id,
            'customer_name' => 'Ana Pérez',
            'total' => 5600,
            'status' => 'sent_to_whatsapp',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 2800,
            'line_total' => 5600,
        ]);
    }

    public function test_order_rejects_a_product_from_another_shop(): void
    {
        $shop = Shop::factory()->create(['slug' => 'tienda-a']);
        $otherShop = Shop::factory()->create(['slug' => 'tienda-b']);
        $product = Product::factory()->create([
            'shop_id' => $otherShop->id,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $this->postJson(route('orders.store', $shop), [
            'items' => [['id' => $product->public_id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_dynamic_attribute_values_filter_only_products_in_the_current_shop(): void
    {
        $shop = Shop::factory()->create(['slug' => 'perfumes-filtro']);
        $otherShop = Shop::factory()->create(['slug' => 'otra-tienda']);
        $definition = AttributeDefinition::create([
            'shop_id' => $shop->id,
            'name' => 'Marca',
            'slug' => 'marca',
            'filterable' => true,
        ]);
        $matching = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Hawas Ice',
            'moderation_status' => ProductModerationStatus::Active,
        ]);
        ProductAttributeValue::create([
            'product_id' => $matching->id,
            'attribute_definition_id' => $definition->id,
            'value' => 'Rasasi',
        ]);
        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Lattafa Khamrah',
            'moderation_status' => ProductModerationStatus::Active,
        ]);
        Product::factory()->create([
            'shop_id' => $otherShop->id,
            'name' => 'Rasasi de otra tienda',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get(route('shops.show', $shop).'?atributo%5Bmarca%5D%5B%5D=Rasasi');

        $response->assertOk()
            ->assertSee('Hawas Ice')
            ->assertDontSee('Lattafa Khamrah')
            ->assertDontSee('Rasasi de otra tienda');
    }

    public function test_csv_inventory_import_requires_preview_and_imports_valid_rows(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'slug' => 'importacion-demo']);
        $csv = "nombre,codigo,categoria,marca,precio,stock,atributos\nHawas Ice,HW-01,,Rasasi,2800,12,Concentración=EDP;Presentación=100 ml\nFila incompleta,, ,,,0,\n";

        $preview = $this->actingAs($seller)->post(route('seller.shops.products.import.preview', $shop), [
            'file' => UploadedFile::fake()->createWithContent('inventario.csv', $csv),
        ]);

        $preview->assertOk()->assertSee('1 listas')->assertSee('1 necesitan revisión');

        $rows = json_decode($preview->viewData('rows') ? json_encode($preview->viewData('rows')) : '[]', true);
        $this->actingAs($seller)->post(route('seller.shops.products.import.store', $shop), [
            'rows' => json_encode(array_values(array_filter($rows, fn ($row) => $row['valid'] ?? false))),
        ])->assertRedirect(route('seller.shops.products.index', $shop));

        $this->assertDatabaseHas('products', ['shop_id' => $shop->id, 'product_code' => 'HW-01', 'brand' => 'Rasasi']);
        $this->assertDatabaseHas('product_attribute_values', ['value' => 'EDP']);
    }
}
