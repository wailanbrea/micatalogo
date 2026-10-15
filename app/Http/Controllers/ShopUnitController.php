<?php

namespace App\Http\Controllers;

use App\Models\{Shop, ShopSyncRecord};
use App\Services\CatalogTaxonomyWebCommands;
use Illuminate\Http\Request;

class ShopUnitController extends Controller
{
    public function store(Request $request, Shop $shop)
    {
        return app(CatalogTaxonomyWebCommands::class)->execute($request, $shop, 'unit', $this->validateUnit($request));
    }

    public function update(Request $request, Shop $shop, string $unit)
    {
        $record = ShopSyncRecord::where('shop_id', $shop->id)->where('kind', 'unit')->where('record_uuid', $unit)->firstOrFail();
        return app(CatalogTaxonomyWebCommands::class)->execute($request, $shop, 'unit', $this->validateUnit($request), unit: $record);
    }

    public function destroy(Request $request, Shop $shop, string $unit)
    {
        $record = ShopSyncRecord::where('shop_id', $shop->id)->where('kind', 'unit')->where('record_uuid', $unit)->firstOrFail();
        $validated = $request->validate(['expected_revision' => ['sometimes', 'nullable', 'uuid'], 'client_operation_uuid' => ['sometimes', 'uuid']]);
        $validated['expected_revision'] ??= $record->payload['revision'] ?? null;
        return app(CatalogTaxonomyWebCommands::class)->execute($request, $shop, 'unit', $validated, unit: $record, deleted: true);
    }

    private function validateUnit(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'min:1', 'max:100'], 'abbreviation' => ['required', 'string', 'max:12'],
            'status' => ['required', 'in:active,inactive'], 'scope' => ['sometimes', 'in:shared,product,service'],
            'expected_revision' => ['sometimes', 'nullable', 'uuid'], 'client_operation_uuid' => ['sometimes', 'uuid'], 'record_id' => ['sometimes', 'uuid']]);
    }
}
