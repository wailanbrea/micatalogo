<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\CatalogMediaService;
use App\Services\InventoryImportService;
use App\Services\PlanLimitsService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryImportController extends Controller
{
    public function preview(
        Request $request,
        Shop $shop,
        InventoryImportService $importer,
        PlanLimitsService $limits,
        SellerMenuService $menus,
    ): JsonResponse {
        $this->authorizeImport($shop, $request, $limits, $menus);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        $rows = $importer->parse($request->file('file'));

        return response()->json([
            'quota' => $limits->shopQuota($shop),
            'rows' => $rows,
            'valid_rows' => collect($rows)->where('valid', true)->count(),
            'invalid_rows' => collect($rows)->where('valid', false)->count(),
        ]);
    }

    public function store(
        Request $request,
        Shop $shop,
        InventoryImportService $importer,
        PlanLimitsService $limits,
        CatalogMediaService $catalogMedia,
        SellerMenuService $menus,
    ): JsonResponse {
        $this->authorizeImport($shop, $request, $limits, $menus);
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1500'],
            'rows.*' => ['required', 'array'],
        ]);

        $imported = $importer->persist($shop, $validated['rows'], $limits, $catalogMedia);

        return response()->json([
            'message' => "Se importaron {$imported} productos después de validar el archivo.",
            'imported' => $imported,
            'quota' => $limits->shopQuota($shop->fresh()),
        ], 201);
    }

    private function authorizeImport(Shop $shop, Request $request, PlanLimitsService $limits, SellerMenuService $menus): void
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        $limits->assertFeature($shop->user, 'bulk_import');
    }
}
