<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\BusinessCapabilityService;
use App\Services\DecantWorkspaceService;
use App\Services\SellerMenuService;
use Illuminate\Http\Request;

class DecantController extends Controller
{
    public function inventoryValue(Request $request, Shop $shop, DecantWorkspaceService $service)
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);
        $finance = $request->user()->id === $shop->user_id || $request->user()->isAdmin()
            || in_array('finance', app(SellerMenuService::class)->visibleForUser($shop, $request->user()), true);

        return response()->json(['inventory_value_cents' => $finance ? $service->inventoryValue($shop, app(\App\Services\DecantInventoryService::class)->available()) : null]);
    }

    public function index(Request $request, Shop $shop, DecantWorkspaceService $service)
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);
        app(BusinessCapabilityService::class)->assert($shop, 'decants');
        $finance = $request->user()->id === $shop->user_id || $request->user()->isAdmin()
            || in_array('finance', app(SellerMenuService::class)->visibleForUser($shop, $request->user()), true);

        return response()->json($service->snapshot($shop, $finance) + ['can_manage' => \Illuminate\Support\Facades\Gate::forUser($request->user())->allows('update', $shop)]);
    }
}
