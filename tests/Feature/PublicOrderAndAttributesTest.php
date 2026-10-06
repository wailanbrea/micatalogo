<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Enums\UserPlan;
use App\Models\AttributeDefinition;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashRegisterService;
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
            ->assertJsonPath('confirmation_url', fn (string $value) => str_contains($value, 'signature='))
            ->assertJsonPath('whatsapp_url', fn (string $value) => str_contains($value, 'wa.me/'));

        $message = urldecode((string) parse_url($response->json('whatsapp_url'), PHP_URL_QUERY));
        expect($message)->toContain('Confirmar pedido y registrar pago:')
            ->and($message)->toContain($response->json('order_number'));

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

    public function test_public_order_can_be_confirmed_into_stock_invoice_and_a_balanced_cash_close(): void
    {
        $owner = User::factory()->create(['plan' => UserPlan::Pro]);
        $shop = Shop::factory()->for($owner)->create([
            'whatsapp_country_code' => '1',
            'whatsapp_number' => '8298144525',
        ]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'price' => 400,
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
        ]);
        ProductInventory::create([
            'product_id' => $product->id,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'cost_price' => 150,
            'sold_quantity' => 0,
        ]);

        $cash = app(CashRegisterService::class);
        $session = $cash->openSession($shop, $owner, 0, 'Prueba de pedido público');

        $created = $this->postJson(route('orders.store', $shop), [
            'items' => [['id' => $product->public_id, 'quantity' => 2]],
            'customer_name' => 'Cliente WhatsApp',
        ])->assertCreated()
            ->assertJsonPath('whatsapp_url', fn (string $url) => str_contains($url, 'wa.me/18298144525'));

        $order = Order::query()->where('order_number', $created->json('order_number'))->firstOrFail();

        $this->actingAs($owner)
            ->post(route('seller.shops.orders.confirm', [$shop, $order]))
            ->assertRedirect();

        $invoice = $order->fresh()->invoice_id ? Invoice::findOrFail($order->fresh()->invoice_id) : null;
        expect($order->fresh()->status)->toBe('confirmed')
            ->and($invoice)->not->toBeNull()
            ->and((float) $invoice->total)->toBe(800.0)
            ->and($product->fresh()->inventory->stock_quantity)->toBe(3)
            ->and($session->fresh()->calculateExpectedBalance())->toBe(80000);

        $closed = $cash->closeSession($session->fresh(), $owner, 800, 'Cuadre automático de prueba');
        expect($closed->status)->toBe('closed')
            ->and($closed->difference_cents)->toBe(0);
        $this->assertDatabaseHas('cash_register_sessions', ['id' => $session->id, 'status' => 'closed']);
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

    public function test_public_decant_order_uses_live_source_ml_and_snapshots_the_selected_presentation(): void
    {
        $owner = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $owner->id,
            'slug' => 'decants-live-stock',
            'status' => 'active',
        ]);
        $source = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Botella fuente',
            'price' => 3000,
            'sale_unit' => 'bottle',
            'volume_ml' => 100,
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
        ]);
        ProductInventory::create([
            'product_id' => $source->id,
            'track_inventory' => true,
            'stock_quantity' => 1,
            'available_ml' => 37,
            'cost_price' => 1000,
        ]);
        $decant = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Botella fuente 5 ml',
            'price' => 100,
            'sale_unit' => 'decant',
            'volume_ml' => 5,
            'inventory_source_product_id' => $source->id,
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
        ]);
        ProductInventory::create([
            'product_id' => $decant->id,
            'track_inventory' => true,
            'stock_quantity' => 99,
        ]);

        $this->postJson(route('orders.store', $shop), [
            'items' => [['id' => $decant->public_id, 'quantity' => 8]],
        ])->assertStatus(422);

        $created = $this->postJson(route('orders.store', $shop), [
            'items' => [['id' => $decant->public_id, 'quantity' => 2]],
        ])->assertCreated();

        $order = Order::query()
            ->where('order_number', $created->json('order_number'))
            ->with('items')
            ->firstOrFail();

        expect($order->items->sole()->product_name)->toBe('Botella fuente 5 ml (5 ml)')
            ->and((float) $order->items->sole()->unit_price)->toBe(100.0)
            ->and($created->json('whatsapp_url'))->toContain('5%20ml');

        $this->actingAs($owner)
            ->post(route('seller.shops.orders.confirm', [$shop, $order]))
            ->assertRedirect();

        expect($source->fresh()->inventory->available_ml)->toBe(27)
            ->and($decant->fresh()->inventory->stock_quantity)->toBe(5)
            ->and($decant->fresh()->inventory->sold_quantity)->toBe(2);
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
        $seller = User::factory()->create(['plan' => UserPlan::Pro]);
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
