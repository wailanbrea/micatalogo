<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class DecantWorkspaceService
{
    public function snapshot(Shop $shop, bool $finance): array
    {
        $service = app(DecantInventoryService::class);
        $available = $service->available();
        $products = $shop->products()->with(['inventory', 'images', 'sourceProduct.inventory'])->where('sale_unit', 'decant')->get();
        $sources = $shop->products()->with(['inventory', 'images'])->whereIn('sale_unit', ['bottle', 'ml'])->orderBy('name')->get();
        $vials = $available ? DB::table('decant_vials')->where('shop_id', $shop->id)->orderBy('volume_ml')->get()->map(function ($vial) use ($finance): array {
            $lots = DB::table('decant_vial_lots')->where('vial_id', $vial->id)->orderBy('received_at')->orderBy('id')->get();
            $next = $lots->first(fn ($lot) => $lot->remaining_quantity > 0);

            return ['id' => $vial->public_id, 'name' => $vial->name, 'volume_ml' => $vial->volume_ml, 'active' => (bool) $vial->active,
                'empty' => (int) $lots->sum('remaining_quantity'), 'investment_cents' => $finance ? (int) $lots->sum('remaining_cost_cents') : null,
                'next_cost_cents' => $finance && $next ? intdiv($next->remaining_cost_cents, $next->remaining_quantity) : null,
                'lots' => $lots->map(fn ($lot) => ['id' => $lot->id, 'received_at' => $lot->received_at,
                    'received' => $lot->received_quantity, 'remaining' => $lot->remaining_quantity,
                    'cost_cents' => $finance ? intdiv($lot->received_cost_cents, $lot->received_quantity) : null])->all()];
        })->all() : [];
        $vialBySize = collect($vials)->where('active', true)->keyBy('volume_ml');
        $groups = $sources->map(function (Product $source) use ($products, $available, $service, $finance, $vialBySize): array {
            $tracked = $available && $service->managed($source);
            $openings = $tracked ? DB::table('decant_openings')->where('product_id', $source->id)->where('status', 'open')->orderBy('id')->get() : collect();
            $sizes = $products->where('inventory_source_product_id', $source->id)->sortBy('volume_ml')->map(function (Product $product) use ($available, $tracked, $service, $finance, $vialBySize, $openings): array {
                $vial = $vialBySize->get($product->volume_ml);
                $prepared = $available ? $service->prepared($product) : 0;
                $price = Money::toCents($product->currentPriceDecimal());
                $sales = DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                    ->where('invoice_items.product_id', $product->id)->where('invoices.created_at', '>=', now()->startOfMonth())->where('invoices.status', '!=', 'cancelled')
                    ->select('invoice_items.*')->get();
                $returns = DB::table('invoice_return_items')->join('invoice_items', 'invoice_items.id', '=', 'invoice_return_items.invoice_item_id')
                    ->where('invoice_items.product_id', $product->id)->where('invoice_return_items.created_at', '>=', now()->startOfMonth())->select('invoice_return_items.*')->get();
                $revenue = $sales->sum(fn ($item) => Money::toCents($item->line_total) - Money::toCents($item->tax) - (int) $item->general_discount_cents)
                    - $returns->sum(fn ($item) => Money::toCents($item->refund) - Money::toCents($item->tax_refund));
                $cost = $sales->contains(fn ($item) => $item->total_cost_cents === null) ? null :
                    $sales->sum('total_cost_cents') - $returns->where('restock', true)->sum('total_cost_cents');
                $capacity = $tracked ? $service->capacity($product) : 0;
                $offered = $available ? $service->offered($product) : $product->moderation_status === \App\Enums\ProductModerationStatus::Active;
                $onDemand = $available ? $service->onDemand($product) : true;
                $threshold = (int) ($product->inventory?->low_stock_threshold ?? 2);
                $state = $prepared > 0 ? 'listo' : ($onDemand && $capacity > 0 ? 'a_pedido' : 'agotado');
                $locked = $available && (bool) DB::table('decant_presentation_settings')->where('product_id', $product->id)->value('price_locked');

                return ['id' => $product->public_id, 'name' => $product->name, 'volume_ml' => $product->volume_ml,
                    'price_cents' => $price, 'prepared' => $prepared, 'capacity' => $capacity,
                    'offered' => $offered, 'state' => $state, 'low' => $threshold > 0 && $prepared > 0 && $prepared <= $threshold,
                    'low_threshold' => $threshold, 'price_source' => $locked ? 'fijado' : ($available && DB::table('decant_batches')->where('product_id', $product->id)->exists() ? 'al preparar' : 'catálogo'),
                    'legacy_capacity' => $tracked ? 0 : (int) ($product->inventory?->stock_quantity ?? 0),
                    'on_demand' => $onDemand, 'empty_vials' => $vial['empty'] ?? 0,
                    'vial_cost_cents' => $finance ? ($vial['next_cost_cents'] ?? null) : null,
                    'sold_month' => (int) $sales->sum('quantity') - (int) $returns->sum('quantity'), 'revenue_month_cents' => $revenue,
                    'profit_month_cents' => $finance && $cost !== null ? $revenue - $cost : null];
            })->values();
            $openingRows = $openings->map(function ($opening) use ($finance): array {
                $batchIds = DB::table('decant_batches')->where('opening_id', $opening->id)->pluck('id');
                $sales = DB::table('decant_batch_sales')->join('inventory_movements', 'inventory_movements.id', '=', 'decant_batch_sales.movement_id')
                    ->whereIn('batch_id', $batchIds)->select('decant_batch_sales.quantity', 'inventory_movements.unit_price', 'inventory_movements.invoice_id', 'inventory_movements.product_id')->get();
                $revenue = $sales->sum(fn ($sale) => $this->netRevenueShare($sale->invoice_id, $sale->product_id, $sale->quantity, $sale->unit_price));
                $remainderSales = DB::table('decant_remainder_sales')->join('inventory_movements', 'inventory_movements.id', '=', 'decant_remainder_sales.movement_id')
                    ->where('opening_id', $opening->id)->select('inventory_movements.invoice_id', 'inventory_movements.product_id', 'inventory_movements.unit_price')->get();
                $revenue += $remainderSales->sum(fn ($sale) => $this->netRevenueShare($sale->invoice_id, $sale->product_id, 1, $sale->unit_price));

                return ['id' => $opening->public_id, 'opened_at' => $opening->created_at, 'initial_ml' => $opening->initial_ml,
                    'remaining_ml' => $opening->remaining_ml, 'lost_ml' => $opening->lost_ml,
                    'cost_cents' => $finance ? $opening->initial_cost_cents : null,
                    'remaining_cost_cents' => $finance ? $opening->remaining_cost_cents : null,
                    'revenue_cents' => $revenue, 'can_undo' => $opening->remaining_ml === $opening->initial_ml && $opening->initial_cost_cents !== null && $opening->lost_ml === 0 && $batchIds->isEmpty()
                        && ! DB::table('decant_remainder_sales')->where('opening_id', $opening->id)->exists()];
            })->all();

            return ['id' => $source->public_id, 'name' => $source->name, 'brand' => $source->brand, 'image_url' => $source->image_url,
                'volume_ml' => $source->volume_ml, 'sealed' => $source->sale_unit === 'bottle' ? (int) ($source->inventory?->stock_quantity ?? 0) : 0, 'tracked' => $tracked,
                'legacy_ml' => $tracked ? 0 : ($source->inventory?->reserved_decant_ml ?? $source->inventory?->available_ml),
                'untracked_open_ml' => max(0, (int) $source->inventory?->available_ml - (int) $source->inventory?->stock_quantity * (int) $source->volume_ml - (int) $openings->sum('remaining_ml')),
                'source_cost_cents' => $finance ? $this->nextBottleCost($source) : null,
                'sizes' => $sizes->all(), 'openings' => $openingRows];
        })->values()->all();
        $sizes = collect($groups)->flatMap(fn ($group) => $group['sizes']);
        $openings = collect($groups)->flatMap(fn ($group) => $group['openings']);

        return ['enabled' => $available, 'finance' => $finance, 'period' => now()->format('Y-m'), 'groups' => $groups, 'vials' => $vials,
            'inventory_value_cents' => $finance ? $this->inventoryValue($shop, $available) : null,
            'prepared' => (int) $sizes->sum('prepared'), 'prepared_value_cents' => (int) $sizes->sum(fn ($size) => $size['prepared'] * $size['price_cents']),
            'on_demand' => $sizes->where('offered', true)->where('state', 'a_pedido')->count(), 'opened' => $openings->where('remaining_ml', '>', 0)->count(),
            'unreconciled_ml' => (int) collect($groups)->sum('untracked_open_ml'),
            'lost_ml' => (int) $openings->sum('lost_ml'), 'empty_vials' => (int) collect($vials)->sum('empty'),
            'vial_investment_cents' => $finance ? (int) collect($vials)->sum('investment_cents') : null];
    }

    private function netRevenueShare(?int $invoiceId, int $productId, int $quantity, string|float|null $price): int
    {
        if ($invoiceId === null) return $quantity * Money::toCents($price ?? 0);
        $item = DB::table('invoice_items')->where('invoice_id', $invoiceId)->where('product_id', $productId)->first();
        if (! $item) return 0;
        $returns = DB::table('invoice_return_items')->where('invoice_item_id', $item->id)->get();
        $net = Money::toCents($item->line_total) - Money::toCents($item->tax) - (int) $item->general_discount_cents
            - $returns->sum(fn ($returned) => Money::toCents($returned->refund) - Money::toCents($returned->tax_refund));

        return intdiv($net * $quantity, max(1, (int) $item->quantity));
    }

    private function nextBottleCost(Product $source): ?int
    {
        if ($source->sale_unit !== 'bottle' || ! $source->volume_ml) return null;
        $lots = DB::table('inventory_lots')->where('product_id', $source->id)->where('remaining_quantity', '>', 0)->orderBy('received_at')->orderBy('id')->get();
        if ($lots->isEmpty()) return $source->inventory?->cost_price === null ? null : Money::toCents($source->inventory->cost_price);
        $left = (int) $source->volume_ml; $cost = 0;
        foreach ($lots as $lot) {
            if ($left === 0) break;
            if ($lot->remaining_cost_cents === null) return null;
            $used = min($left, $lot->remaining_quantity);
            $cost += $used === $lot->remaining_quantity ? $lot->remaining_cost_cents : intdiv($lot->remaining_cost_cents * $used, $lot->remaining_quantity);
            $left -= $used;
        }
        return $left === 0 ? $cost : null;
    }

    public function inventoryValue(Shop $shop, bool $available): int
    {
        $ids = $shop->products()->withTrashed()->select('id');
        $value = (int) DB::table('inventory_lots')->whereIn('product_id', $ids)->sum('remaining_cost_cents');
        $value += (int) DB::table('product_inventories')->join('products', 'products.id', '=', 'product_inventories.product_id')
            ->where('products.shop_id', $shop->id)->where('product_inventories.track_inventory', true)
            ->where(fn ($q) => $q->whereNull('products.sale_unit')->orWhere('products.sale_unit', '!=', 'decant'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('inventory_lots')->whereColumn('inventory_lots.product_id', 'products.id'))
            ->selectRaw('COALESCE(SUM(ROUND(product_inventories.stock_quantity * product_inventories.cost_price * 100)), 0) AS cents')->value('cents');
        if ($available) {
            $value += (int) DB::table('decant_openings')->whereIn('product_id', $ids)->where('status', 'open')->sum('remaining_cost_cents');
            $value += (int) DB::table('decant_batches')->whereIn('product_id', $ids)->sum('remaining_cost_cents');
            $value += (int) DB::table('decant_vial_lots')->whereIn('vial_id', DB::table('decant_vials')->where('shop_id', $shop->id)->select('id'))->sum('remaining_cost_cents');
        }
        return $value;
    }
}
