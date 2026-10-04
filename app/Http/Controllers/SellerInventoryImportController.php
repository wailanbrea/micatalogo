<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\CatalogMediaService;
use App\Services\InventoryImportService;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerInventoryImportController extends Controller
{
    public function create(Shop $shop, PlanLimitsService $limits): View
    {
        $limits->assertFeature($shop->user, 'bulk_import');

        return view('seller.products.import', [
            'shop' => $shop,
            'quota' => $limits->shopQuota($shop),
            'rows' => [],
            'validRows' => 0,
            'invalidRows' => 0,
        ]);
    }

    public function preview(Request $request, Shop $shop, InventoryImportService $importer, PlanLimitsService $limits): View
    {
        $limits->assertFeature($shop->user, 'bulk_import');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        $rows = $importer->parse($request->file('file'));

        return view('seller.products.import', [
            'shop' => $shop,
            'quota' => $limits->shopQuota($shop),
            'rows' => $rows,
            'validRows' => collect($rows)->where('valid', true)->count(),
            'invalidRows' => collect($rows)->where('valid', false)->count(),
        ]);
    }

    public function store(Request $request, Shop $shop, InventoryImportService $importer, PlanLimitsService $limits, CatalogMediaService $catalogMedia): RedirectResponse
    {
        $limits->assertFeature($shop->user, 'bulk_import');

        $request->validate(['rows' => ['required', 'json']]);
        $rows = json_decode($request->string('rows')->value(), true);
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['rows' => 'No hay filas válidas para importar.']);
        }

        $imported = $importer->persist($shop, $rows, $limits, $catalogMedia);

        return to_route('seller.shops.products.index', $shop)->with('status', "Se importaron {$imported} productos después de validar el archivo.");
    }
}
