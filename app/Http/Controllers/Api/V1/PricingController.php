<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ProductPricingService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingController extends Controller
{
    public function recalculate(
        Request $request,
        Shop $shop,
        SellerMenuService $menus,
        ProductPricingService $pricing
    ): JsonResponse {
        abort_unless($menus->canManage($shop, $request->user()), 403);

        $products = $shop->products()
            ->with('inventory')
            ->where('sale_unit', '!=', 'decant')
            ->whereHas('inventory', fn ($query) => $query->whereNotNull('cost_price'))
            ->get();
        $evaluated = 0;

        DB::transaction(function () use ($request, $products, $pricing, &$evaluated): void {
            foreach ($products as $product) {
                if (! DB::table('product_price_rules')->where('product_id', $product->id)->exists()) {
                    continue;
                }
                $pricing->requirePro($product);
                $pricing->propose($product, (float) $product->inventory->cost_price, $request->user()->id);
                $evaluated++;
            }
        });

        return response()->json([
            'message' => $evaluated > 0
                ? "Se recalcularon {$evaluated} regla(s). Las bajadas quedan pendientes de aprobación."
                : 'No hay reglas con costo disponible para recalcular.',
            'evaluated' => $evaluated,
        ]);
    }

    public function storeRule(
        Request $request,
        Shop $shop,
        Product $product,
        SellerMenuService $menus,
        ProductPricingService $pricing
    ): JsonResponse {
        $this->authorizeProduct($request, $shop, $product, $menus);
        $pricing->requirePro($product);

        $data = $request->validate([
            'margin_percent' => ['required', 'numeric', 'min:0', 'max:95'],
            'round_step' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'auto_increase' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($product, $data): void {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            DB::table('product_price_rules')->updateOrInsert(
                ['product_id' => $product->id],
                [
                    'margin_percent' => $data['margin_percent'],
                    'round_step_cents' => (int) round((float) $data['round_step'] * 100),
                    'auto_increase' => (bool) ($data['auto_increase'] ?? false),
                    'pending_price' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        return response()->json(['message' => 'Regla guardada. Se evaluará al recibir mercancía.']);
    }

    public function approve(
        Request $request,
        Shop $shop,
        Product $product,
        SellerMenuService $menus,
        ProductPricingService $pricing
    ): JsonResponse {
        $this->authorizeProduct($request, $shop, $product, $menus);
        $pricing->requirePro($product);
        $data = $request->validate(['expected_price' => ['required', 'decimal:0,2', 'min:0']]);

        DB::transaction(function () use ($request, $product, $pricing, $data): void {
            $lockedProduct = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $rule = DB::table('product_price_rules')->where('product_id', $lockedProduct->id)->lockForUpdate()->first();
            if (! $rule || $rule->pending_price === null || (float) $rule->pending_price !== (float) $data['expected_price']) {
                throw ValidationException::withMessages(['pricing' => 'La propuesta cambió; revisa el precio antes de aprobar.']);
            }
            $pricing->apply($lockedProduct, (float) $rule->pending_price, $request->user()->id, 'approved');
            DB::table('product_price_rules')->where('id', $rule->id)->update(['pending_price' => null, 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Precio aprobado.']);
    }

    private function authorizeProduct(Request $request, Shop $shop, Product $product, SellerMenuService $menus): void
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        abort_unless($product->shop_id === $shop->id, 404);
        abort_if($product->sale_unit === 'decant', 422, 'Los decants usan el precio de su presentación y no una regla independiente.');
    }
}
