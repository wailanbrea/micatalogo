<?php

namespace App\Http\Controllers;

use App\Models\InventoryImportSession;
use App\Models\Shop;
use App\Services\CatalogMediaService;
use App\Services\InventoryImportService;
use App\Services\PlanLimitsService;
use App\Services\SellerMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerInventoryImportController extends Controller
{
    public function create(Request $request, Shop $shop, PlanLimitsService $limits, InventoryImportService $importer, SellerMenuService $menus): View
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        $limits->assertFeature($shop->user, 'bulk_import');

        return view('seller.products.import', [
            'shop' => $shop,
            'quota' => $limits->shopQuota($shop),
            'rows' => [],
            'headers' => [],
            'mapping' => $shop->inventory_import_mapping ?? [],
            'fields' => $importer->fields(),
            'validRows' => 0,
            'invalidRows' => 0,
            'session' => null,
            'sessionId' => null,
            'missingCategories' => [],
            'mojibakeWarning' => false,
            'recentSessions' => $shop->importSessions()->with('user')->take(5)->get(),
        ]);
    }

    public function preview(
        Request $request,
        Shop $shop,
        InventoryImportService $importer,
        PlanLimitsService $limits,
        SellerMenuService $menus,
    ): View {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        $limits->assertFeature($shop->user, 'bulk_import');

        $request->validate([
            'file' => ['required_without:upload_token', 'file', 'mimes:csv,txt,xlsx,xls,pdf', 'extensions:csv,txt,xlsx,xls,pdf', 'max:10240'],
            'upload_token' => ['nullable', 'uuid'],
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:500'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:5050'],
            'sheet_index' => ['nullable', 'integer', 'min:0', 'max:19'],
            'attribute_columns' => ['nullable', 'array', 'max:100'],
            'attribute_columns.*' => ['required', 'string', 'max:500'],
        ]);

        $preview = $importer->preview(
            $request->file('file'),
            $request->input('mapping', $shop->inventory_import_mapping ?? []),
            $shop,
            $request->user(),
            $request->only(['header_row', 'sheet_index', 'attribute_columns', 'upload_token']) + ['manual_mapping' => $request->has('mapping')]
        );

        if ($request->has('mapping') && $preview['session_id']) {
            $shop->update(['inventory_import_mapping' => $preview['mapping']]);
        }

        return view('seller.products.import', [
            'shop' => $shop,
            'detection' => $preview,
            'quota' => $limits->shopQuota($shop),
            'rows' => $preview['rows'],
            'headers' => $preview['headers'],
            'mapping' => $preview['mapping'],
            'fields' => $importer->fields(),
            'validRows' => $preview['valid_rows'],
            'invalidRows' => $preview['invalid_rows'],
            'session' => $preview['session'],
            'sessionId' => $preview['session_id'],
            'missingCategories' => $preview['missing_categories'],
            'mojibakeWarning' => $preview['mojibake_warning'],
            'newRowsCount' => $preview['new_rows_count'],
            'existingRowsCount' => $preview['existing_rows_count'],
            'duplicateRowsCount' => $preview['duplicate_rows_count'],
            'recentSessions' => $shop->importSessions()->with('user')->take(5)->get(),
        ]);
    }

    public function store(
        Request $request,
        Shop $shop,
        InventoryImportService $importer,
        PlanLimitsService $limits,
        CatalogMediaService $catalogMedia,
        SellerMenuService $menus,
    ): RedirectResponse {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        $limits->assertFeature($shop->user, 'bulk_import');

        if ($request->filled('session_id')) {
            $session = InventoryImportSession::query()
                ->where('public_id', $request->string('session_id')->value())
                ->where('shop_id', $shop->id)
                ->firstOrFail();

            $options = [
                'duplicate_strategy' => $request->string('duplicate_strategy', 'skip')->value(),
                'create_missing_categories' => $request->boolean('create_missing_categories', false),
            ];

            $summary = $importer->confirmSession($shop, $session, $options, $limits, $catalogMedia, $request->user());
        } elseif ($request->filled('rows')) {
            // Legacy fallback if raw rows are posted
            $rawRows = json_decode($request->string('rows')->value(), true);
            if (! is_array($rawRows) || $rawRows === []) {
                throw ValidationException::withMessages(['rows' => 'No hay filas válidas para importar.']);
            }
            $summary = $importer->persistSummary($shop, $rawRows, $limits, $catalogMedia, $request->user());
        } else {
            throw ValidationException::withMessages(['session' => 'Debes proporcionar una sesión de importación válida.']);
        }

        $message = "Importación completada: {$summary['created']} creados, {$summary['updated']} actualizados, {$summary['skipped']} omitidos";
        if (($summary['categories_created'] ?? 0) > 0) {
            $message .= ", {$summary['categories_created']} categorías creadas.";
        } else {
            $message .= '.';
        }

        return to_route('seller.shops.products.index', $shop)->with('status', $message);
    }
}
