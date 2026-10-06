<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryImportSession;
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
            'file' => ['required_without:upload_token', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,txt,xlsx,xls', 'max:10240'],
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

        return response()->json([
            ...array_diff_key($preview, ['session' => true]),
            'session_id' => $preview['session_id'],
            'quota' => $limits->shopQuota($shop),
            'headers' => $preview['headers'],
            'mapping' => $preview['mapping'],
            'fields' => $importer->fields(),
            'rows' => $preview['rows'],
            'valid_rows' => $preview['valid_rows'],
            'invalid_rows' => $preview['invalid_rows'],
            'new_rows_count' => $preview['new_rows_count'],
            'existing_rows_count' => $preview['existing_rows_count'],
            'duplicate_rows_count' => $preview['duplicate_rows_count'],
            'missing_categories' => $preview['missing_categories'],
            'mojibake_warning' => $preview['mojibake_warning'],
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

        if ($request->filled('session_id')) {
            $session = InventoryImportSession::query()
                ->where('public_id', $request->string('session_id')->value())
                ->where('shop_id', $shop->id)
                ->firstOrFail();

            return $this->processSessionConfirmation($request, $shop, $session, $importer, $limits, $catalogMedia);
        }

        // Legacy compatibility for clients sending raw rows
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1500'],
            'rows.*' => ['required', 'array'],
        ]);

        $imported = $importer->persist($shop, $validated['rows'], $limits, $catalogMedia, $request->user());

        return response()->json([
            'message' => "Se importaron {$imported} productos después de validar el archivo.",
            'imported' => $imported,
            'summary' => [
                'created' => $imported,
                'updated' => 0,
                'skipped' => 0,
                'categories_created' => 0,
            ],
            'quota' => $limits->shopQuota($shop->fresh()),
        ], 201);
    }

    public function confirm(
        Request $request,
        Shop $shop,
        InventoryImportSession $session,
        InventoryImportService $importer,
        PlanLimitsService $limits,
        CatalogMediaService $catalogMedia,
        SellerMenuService $menus,
    ): JsonResponse {
        $this->authorizeImport($shop, $request, $limits, $menus);

        return $this->processSessionConfirmation($request, $shop, $session, $importer, $limits, $catalogMedia);
    }

    private function processSessionConfirmation(
        Request $request,
        Shop $shop,
        InventoryImportSession $session,
        InventoryImportService $importer,
        PlanLimitsService $limits,
        CatalogMediaService $catalogMedia,
    ): JsonResponse {
        abort_unless($session->shop_id === $shop->id, 403, 'La sesión no pertenece a esta tienda.');

        $options = [
            'duplicate_strategy' => $request->string('duplicate_strategy', 'skip')->value(),
            'create_missing_categories' => $request->boolean('create_missing_categories', false),
            'row_actions' => $request->input('row_actions', []),
        ];

        $summary = $importer->confirmSession($shop, $session, $options, $limits, $catalogMedia, $request->user());
        $totalImported = (int) (($summary['created'] ?? 0) + ($summary['updated'] ?? 0));

        $status = $session->isConfirmed() ? 200 : 201;

        return response()->json([
            'message' => "Importación completada: {$summary['created']} creados, {$summary['updated']} actualizados, {$summary['skipped']} omitidos.",
            'imported' => $totalImported,
            'summary' => $summary,
            'quota' => $limits->shopQuota($shop->fresh()),
        ], $status);
    }

    private function authorizeImport(Shop $shop, Request $request, PlanLimitsService $limits, SellerMenuService $menus): void
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        $limits->assertFeature($shop->user, 'bulk_import');
    }
}
