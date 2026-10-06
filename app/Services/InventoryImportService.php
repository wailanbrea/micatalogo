<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Jobs\ResolveProductCatalogMediaJob;
use App\Models\AttributeDefinition;
use App\Models\GlobalCategory;
use App\Models\InventoryImportSession;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryImport\ColumnDetector;
use App\Services\InventoryImport\WorkbookReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryImportService
{
    /**
     * Preview inventory file, normalize columns, detect duplicates, and create a server-side session.
     *
     * @return array<string, mixed>
     */
    public function preview(?UploadedFile $file, array $mapping = [], ?Shop $shop = null, ?User $user = null, array $options = []): array
    {
        $started = microtime(true);
        $uploadToken = $options['upload_token'] ?? null;
        if ($file) {
            $book = app(WorkbookReader::class)->read($file);
            $filename = basename($file->getClientOriginalName());
            if ($shop && $user) {
                $uploadToken = (string) Str::uuid();
                Cache::put('inventory-upload:'.$uploadToken, ['shop_id' => $shop->id, 'user_id' => $user->id, 'book' => $book, 'filename' => $filename], now()->addHours(2));
            }
        } else {
            $cached = Cache::get('inventory-upload:'.$uploadToken);
            if (! $cached) {
                throw ValidationException::withMessages(['file' => 'El archivo temporal ha expirado. Vuelve a cargarlo.']);
            }
            abort_unless($shop && $user && $cached['shop_id'] === $shop->id && $cached['user_id'] === $user->id, 403);
            $book = $cached['book'];
            $filename = $cached['filename'];
        }
        $detector = app(ColumnDetector::class);
        $sheets = [];
        foreach ($book['sheets'] as $sheet) {
            $header = $detector->header($sheet['rows']);
            $sheets[] = ['name' => $sheet['name'], 'index' => $sheet['index'], 'confidence' => $header['confidence'], 'header_row' => $header['row'], 'data_rows' => count(array_filter(array_slice($sheet['rows'], $header['row']), fn ($r) => count(array_filter($r, fn ($v) => trim((string) $v) !== '')) > 0))];
        }
        $ranked = $sheets;
        usort($ranked, fn ($a, $b) => ($b['confidence'] <=> $a['confidence']) ?: ($b['data_rows'] <=> $a['data_rows']) ?: ($a['index'] <=> $b['index']));
        $selectedIndex = isset($options['sheet_index']) ? (int) $options['sheet_index'] : ($ranked[0]['index'] ?? 0);
        $selected = $book['sheets'][$selectedIndex] ?? null;
        if (! $selected) {
            throw ValidationException::withMessages(['sheet_index' => 'Selecciona una hoja válida del archivo.']);
        }
        $rawRows = $selected['rows'];
        $header = $detector->header($rawRows, isset($options['header_row']) ? (int) $options['header_row'] : null);
        $originalHeaders = $header['values'];
        $filteredRows = array_filter(array_slice($rawRows, $header['row'], null, true), fn ($row) => count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0);
        if (count($filteredRows) > 5000) {
            throw ValidationException::withMessages(['file' => 'Solo se permiten 5000 productos por importación.']);
        }
        $detection = $detector->mapping($originalHeaders, array_values($filteredRows), $mapping, (bool) ($options['manual_mapping'] ?? true));
        $headers = $detection['headers'];
        $mapping = $detection['mapping'];
        $attributeColumns = (array) ($options['attribute_columns'] ?? []);
        foreach ($attributeColumns as $source) {
            if (! in_array($source, $headers, true) || in_array($source, $mapping, true)) {
                throw ValidationException::withMessages(['attribute_columns' => 'Selecciona columnas ignoradas válidas para los atributos.']);
            }
        }
        $warnings = array_merge($book['warnings'], $detection['warnings']);
        foreach (['barcode', 'product_code'] as $field) {
            $column = array_search($mapping[$field], $headers, true);
            if ($mapping[$field] !== '' && in_array($column, $selected['unformatted_numeric_columns'] ?? [], true)) {
                $warnings[] = "La columna {$originalHeaders[$column]} contiene códigos numéricos sin formato de ceros. No podemos recuperar ceros que Excel haya eliminado; comprueba la muestra.";
            }
        }
        if ($header['needs_selection']) {
            $warnings[] = 'No pudimos reconocer los encabezados con suficiente confianza. Selecciona la fila manualmente.';
        }

        $mojibakeWarning = $this->detectMojibake($originalHeaders, $filteredRows);
        if ($mojibakeWarning) {
            $warnings[] = 'Se detectaron caracteres posiblemente dañados por codificación. Revisa encabezados y ejemplos antes de importar.';
        }

        // Preload shop data for duplicate & category matching if shop provided
        $existingByBarcode = collect();
        $existingBySku = collect();
        $existingByName = collect();
        $shopCategories = collect();
        $globalCategories = GlobalCategory::query()
            ->where('status', 'active')
            ->get()
            ->keyBy(fn ($cat) => Str::of($cat->name)->squish()->ascii()->lower()->value());

        if ($shop) {
            $existingProducts = $shop->products()
                ->with('inventory')
                ->get(['id', 'shop_id', 'name', 'product_code', 'barcode', 'price', 'shop_category_id']);

            $catalogMedia = app(CatalogMediaService::class);
            $existingByBarcode = $existingProducts->whereNotNull('barcode')
                ->keyBy(fn ($p) => (string) $catalogMedia->normalizeBarcode($p->barcode));

            $existingBySku = $existingProducts->whereNotNull('product_code')
                ->keyBy(fn ($p) => Str::lower(trim($p->product_code)));

            $existingByName = $existingProducts
                ->keyBy(fn ($p) => Str::of($p->name)->squish()->ascii()->lower()->value());

            $shopCategories = $shop->categories()
                ->get()
                ->keyBy(fn ($cat) => Str::of($cat->name)->squish()->ascii()->lower()->value());
        }

        $seenBarcodes = [];
        $seenSkus = [];
        $seenNames = [];
        $missingCategories = [];
        $rows = [];

        foreach ($filteredRows as $index => $rawRow) {
            $line = $index + 1;
            $row = $this->normaliseRow(
                $headers,
                $mapping,
                $rawRow,
                $line,
                $existingByBarcode,
                $existingBySku,
                $existingByName,
                $shopCategories,
                $globalCategories,
                $seenBarcodes,
                $seenSkus,
                $seenNames,
                $missingCategories,
            );
            foreach ($attributeColumns as $source) {
                $column = array_search($source, $headers, true);
                $attributeValue = trim((string) ($rawRow[$column] ?? ''));
                if ($attributeValue !== '') {
                    $row['attributes'][] = ['name' => $originalHeaders[$column], 'value' => $attributeValue];
                }
            }
            if ($header['needs_selection']) {
                $row['valid'] = false;
                $row['status'] = 'invalid';
                $row['errors'][] = 'Selecciona la fila de encabezados antes de confirmar.';
            }
            $rows[] = $row;
        }

        $validRowsCount = collect($rows)->where('valid', true)->count();
        $invalidRowsCount = collect($rows)->where('valid', false)->count();

        // Create a server-side import session if shop is present
        $session = null;
        if ($shop && ! $header['needs_selection'] && $mapping['name'] !== '' && $mapping['price'] !== '' && $rows !== []) {
            $session = InventoryImportSession::create([
                'shop_id' => $shop->id,
                'user_id' => $user?->id,
                'original_filename' => $filename,
                'status' => 'previewed',
                'total_rows' => count($rows),
                'valid_rows' => $validRowsCount,
                'invalid_rows' => $invalidRowsCount,
                'rows_payload' => $rows,
                'missing_categories' => array_keys($missingCategories),
                'mapping' => $mapping,
                'expires_at' => now()->addHours(2),
            ]);
        }

        Log::info('Inventory import preview generated', [
            'shop_id' => $shop?->id,
            'user_id' => $user?->id,
            'session_id' => $session?->public_id,
            'total_rows' => count($rows),
            'valid_rows' => $validRowsCount,
            'invalid_rows' => $invalidRowsCount,
            'missing_categories' => array_keys($missingCategories),
            'filename' => $filename,
            'file_type' => $book['type'],
            'sheet' => $selected['name'],
            'header_row' => $header['row'],
            'mapping' => $mapping,
            'mapping_confidence' => array_map(fn ($detail) => $detail['confidence'], $detection['mapping_confidence']),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return [
            'session_id' => $session?->public_id,
            'session' => $session,
            'headers' => $headers,
            'mapping' => $mapping,
            'fields' => $this->fields(),
            'rows' => $rows,
            'valid_rows' => $validRowsCount,
            'invalid_rows' => $invalidRowsCount,
            'new_rows_count' => collect($rows)->where('status', 'new')->where('valid', true)->count(),
            'existing_rows_count' => collect($rows)->where('status', 'existing')->where('valid', true)->count(),
            'duplicate_rows_count' => collect($rows)->where('status', 'possible_duplicate')->where('valid', true)->count(),
            'missing_categories' => array_keys($missingCategories),
            'mojibake_warning' => $mojibakeWarning,
            'file' => ['type' => $book['type']],
            'upload_token' => $uploadToken,
            'sheet' => ['name' => $selected['name'], 'index' => $selectedIndex, 'confidence' => $header['confidence']],
            'sheets' => $sheets,
            'header_row' => $header['row'],
            'header_candidates' => $header['candidates'],
            'needs_header_selection' => $header['needs_selection'],
            'original_headers' => $originalHeaders,
            'mapping_confidence' => $detection['mapping_confidence'],
            'ignored_columns' => $detection['ignored_columns'],
            'warnings' => $warnings,
            'sample_rows' => array_slice($rows, 0, 10),
            'counts' => ['total' => count($rows), 'valid' => $validRowsCount, 'invalid' => $invalidRowsCount, 'new' => collect($rows)->where('status', 'new')->where('valid', true)->count(), 'existing' => collect($rows)->where('status', 'existing')->where('valid', true)->count(), 'duplicates' => collect($rows)->where('status', 'possible_duplicate')->count(), 'missing_categories' => count($missingCategories)],
        ];
    }

    /**
     * Parse rows directly (compatibility alias).
     */
    public function parse(UploadedFile $file, array $mapping = [], ?Shop $shop = null): array
    {
        return $this->preview($file, $mapping, $shop)['rows'];
    }

    /**
     * Confirm a server-side import session securely.
     *
     * @param  array{duplicate_strategy?: string, create_missing_categories?: bool, row_actions?: array<int, string>}  $options
     * @return array<string, mixed>
     */
    public function confirmSession(
        Shop $shop,
        InventoryImportSession $session,
        array $options,
        PlanLimitsService $limits,
        CatalogMediaService $catalogMedia,
        ?User $user = null,
    ): array {
        abort_unless($session->shop_id === $shop->id, 403, 'La sesión de importación no pertenece a esta tienda.');
        abort_unless(! $session->user_id || ($user && $session->user_id === $user->id), 403, 'La sesión pertenece a otro usuario.');

        // Idempotency: if already confirmed, return existing summary immediately
        if ($session->isConfirmed()) {
            return $session->summary ?? [
                'created' => 0,
                'updated' => 0,
                'skipped' => $session->total_rows,
                'errors' => 0,
                'categories_created' => 0,
            ];
        }

        if ($session->isExpired()) {
            $session->update(['status' => 'expired']);
            throw ValidationException::withMessages(['session' => 'La sesión de importación ha expirado. Vuelve a cargar el archivo.']);
        }

        if ($session->status !== 'previewed') {
            throw ValidationException::withMessages(['session' => 'La sesión de importación no está disponible para confirmación.']);
        }

        $strategy = $options['duplicate_strategy'] ?? 'skip';
        $duplicateStrategy = in_array($strategy, ['skip', 'update', 'create'], true) ? $strategy : 'skip';
        $createMissingCategories = (bool) ($options['create_missing_categories'] ?? false);
        $rowActions = (array) ($options['row_actions'] ?? []);

        $rows = $session->rows_payload ?: [];
        if ($rows === []) {
            throw ValidationException::withMessages(['session' => 'No hay filas en la sesión de importación.']);
        }

        Log::info('Inventory import confirmation initiated', [
            'shop_id' => $shop->id,
            'user_id' => $user?->id,
            'session_id' => $session->public_id,
            'strategy' => $duplicateStrategy,
            'create_missing_categories' => $createMissingCategories,
        ]);

        $summary = [];
        $productIdsToResolveMedia = [];

        DB::transaction(function () use (
            $shop,
            $session,
            $rows,
            $duplicateStrategy,
            $createMissingCategories,
            $rowActions,
            $limits,
            $catalogMedia,
            $user,
            &$summary,
            &$productIdsToResolveMedia,
        ): void {
            $lockedShop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $lockedSession = InventoryImportSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($lockedSession->isConfirmed()) {
                $summary = $lockedSession->summary;

                return;
            }
            if ($lockedSession->isExpired() || $lockedSession->status !== 'previewed') {
                throw ValidationException::withMessages(['session' => 'La sesión ya no está disponible.']);
            }

            // Determine effective action for each row
            $currentBarcodes = $lockedShop->products()->whereNotNull('barcode')->pluck('barcode')->mapWithKeys(fn ($barcode) => [$catalogMedia->normalizeBarcode($barcode) => true])->all();
            $rowsToProcess = [];
            foreach ($rows as $row) {
                $line = (int) ($row['line'] ?? 0);
                if (! ($row['valid'] ?? false)) {
                    $action = 'skip';
                } elseif (isset($rowActions[$line]) && in_array($rowActions[$line], ['create', 'update', 'skip'], true)) {
                    $action = $rowActions[$line];
                } elseif (($row['status'] ?? 'new') === 'existing') {
                    $action = $duplicateStrategy;
                } elseif (($row['status'] ?? 'new') === 'possible_duplicate') {
                    $action = $duplicateStrategy;
                } else {
                    $action = 'create';
                }

                // Recheck identities under the shop lock before quota accounting, including stale previews.
                $barcode = $catalogMedia->normalizeBarcode($row['barcode'] ?? null);
                if ($action === 'create' && $barcode && isset($currentBarcodes[$barcode])) {
                    $action = 'skip';
                    $row['valid'] = false;
                } elseif ($action === 'create' && $barcode) {
                    $currentBarcodes[$barcode] = true;
                }

                $row['effective_action'] = $action;
                $rowsToProcess[] = $row;
            }

            // Quota: ONLY rows with action === 'create' consume product quota!
            $newProductsCount = collect($rowsToProcess)->where('effective_action', 'create')->count();
            if ($newProductsCount > 0) {
                try {
                    $limits->assertCanAddProducts($lockedShop, $newProductsCount, 'rows');
                } catch (ValidationException $e) {
                    Log::warning('Inventory import plan quota exceeded', [
                        'shop_id' => $lockedShop->id,
                        'user_id' => $user?->id,
                        'session_id' => $session->public_id,
                        'requested_new_products' => $newProductsCount,
                    ]);
                    throw $e;
                }
            }

            // Preload and cache categories
            $categories = $lockedShop->categories()->get()->keyBy(fn ($c) => Str::of($c->name)->squish()->ascii()->lower()->value());
            $globalCategories = GlobalCategory::query()
                ->where('status', 'active')
                ->get()
                ->keyBy(fn ($c) => Str::of($c->name)->squish()->ascii()->lower()->value());

            $categoriesCreated = 0;
            if ($createMissingCategories && ! empty($session->missing_categories)) {
                foreach ($session->missing_categories as $missingCatName) {
                    $key = Str::of($missingCatName)->squish()->ascii()->lower()->value();
                    if ($key !== '' && ! $categories->has($key)) {
                        $baseSlug = Str::slug($missingCatName) ?: 'cat';
                        $slug = $baseSlug;
                        $suffix = 2;
                        while ($lockedShop->categories()->where('slug', $slug)->exists()) {
                            $slug = $baseSlug.'-'.$suffix++;
                        }
                        $newCat = $lockedShop->categories()->create([
                            'name' => trim((string) $missingCatName),
                            'slug' => $slug,
                            'status' => 'active',
                        ]);
                        $categories->put($key, $newCat);
                        $categoriesCreated++;
                    }
                }
            }

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = collect($rowsToProcess)->where('valid', false)->count();

            foreach ($rowsToProcess as $row) {
                $action = $row['effective_action'];

                if ($action === 'skip') {
                    $skippedCount++;

                    continue;
                }

                $catKey = Str::of((string) ($row['category'] ?? ''))->squish()->ascii()->lower()->value();
                $category = $categories->get($catKey);
                $globalCategory = $globalCategories->get($catKey);

                if ($action === 'create') {
                    $normalizedBarcode = $catalogMedia->normalizeBarcode($row['barcode'] ?? null);
                    if ($normalizedBarcode !== null && $lockedShop->products()->where('barcode', $normalizedBarcode)->exists()) {
                        Log::warning('Inventory import disallowed creating product with duplicate barcode in shop', [
                            'shop_id' => $lockedShop->id,
                            'barcode' => $normalizedBarcode,
                            'name' => $row['name'] ?? null,
                            'line' => $row['line'] ?? null,
                        ]);
                        $skippedCount++;
                        $errorCount++;

                        continue;
                    }

                    $slug = $this->uniqueSlug($lockedShop, (string) $row['name']);
                    $stock = array_key_exists('stock', $row) && $row['stock'] !== null ? max(0, (int) $row['stock']) : 0;
                    $trackInventory = ($row['stock'] ?? null) !== null || ($row['cost_price'] ?? null) !== null;

                    $product = $lockedShop->products()->create([
                        'name' => trim((string) $row['name']),
                        'product_code' => $row['product_code'] ?? null,
                        'barcode' => $normalizedBarcode,
                        'brand' => $row['brand'] ?? null,
                        'slug' => $slug,
                        'description' => $row['description'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'shop_category_id' => $category?->id,
                        'global_category_id' => $globalCategory?->id,
                        'price' => $row['price'],
                        'currency' => config('catalog.currency', 'DOP'),
                        'availability_status' => $trackInventory && $stock === 0 ? ProductAvailabilityStatus::OutOfStock : ProductAvailabilityStatus::Available,
                        'moderation_status' => ProductModerationStatus::Active,
                        'published_at' => now(),
                    ]);

                    $productIdsToResolveMedia[] = $product->id;

                    $product->inventory()->create([
                        'track_inventory' => $trackInventory,
                        'cost_price' => $row['cost_price'] ?? null,
                        'stock_quantity' => $stock,
                        'low_stock_threshold' => 3,
                    ]);

                    $this->persistAttributes($lockedShop, $product, $category?->id, $row['attributes'] ?? []);
                    $createdCount++;
                } elseif ($action === 'update') {
                    $matchedId = $row['matched_product_id'] ?? null;
                    $product = null;
                    if ($matchedId) {
                        $product = $lockedShop->products()->lockForUpdate()->find($matchedId);
                    }
                    if (! $product && ! empty($row['barcode'])) {
                        $normalizedBarcode = $catalogMedia->normalizeBarcode($row['barcode']);
                        $product = $lockedShop->products()->lockForUpdate()->where('barcode', $normalizedBarcode)->first();
                    }
                    if (! $product && ! empty($row['product_code'])) {
                        $product = $lockedShop->products()->lockForUpdate()->where('product_code', $row['product_code'])->first();
                    }

                    if (! $product) {
                        // Could not locate product for update, skip
                        $skippedCount++;

                        continue;
                    }

                    $updateData = [
                        'name' => trim((string) $row['name']),
                        'price' => $row['price'],
                    ];
                    if (! empty($row['brand'])) {
                        $updateData['brand'] = $row['brand'];
                    }
                    if (! empty($row['description'])) {
                        $updateData['description'] = $row['description'];
                    }
                    if (! empty($row['notes'])) {
                        $updateData['notes'] = $row['notes'];
                    }
                    if (! empty($row['product_code'])) {
                        $updateData['product_code'] = $row['product_code'];
                    }
                    if (! empty($row['barcode'])) {
                        $updateData['barcode'] = $catalogMedia->normalizeBarcode($row['barcode']);
                    }
                    if ($category) {
                        $updateData['shop_category_id'] = $category->id;
                    }
                    if ($globalCategory) {
                        $updateData['global_category_id'] = $globalCategory->id;
                    }

                    $product->update($updateData);

                    // Update inventory
                    $inventory = $product->inventory()->first();
                    $newStock = array_key_exists('stock', $row) && $row['stock'] !== null ? max(0, (int) $row['stock']) : null;
                    $newCost = $row['cost_price'] ?? null;

                    if ($inventory) {
                        if ($newCost !== null) {
                            $inventory->cost_price = $newCost;
                        }
                        if ($newStock !== null) {
                            if ($inventory->track_inventory) {
                                try {
                                    app(InventoryService::class)->adjustStock(
                                        $product,
                                        $newStock,
                                        'Actualización por importación de inventario',
                                        $user?->id
                                    );
                                } catch (InvalidArgumentException) {
                                    $inventory->stock_quantity = $newStock;
                                    $inventory->save();
                                }
                            } else {
                                $inventory->stock_quantity = $newStock;
                                $inventory->save();
                            }
                        } else {
                            $inventory->save();
                        }
                    } elseif ($newStock !== null || $newCost !== null) {
                        $product->inventory()->create([
                            'track_inventory' => true,
                            'cost_price' => $newCost,
                            'stock_quantity' => $newStock ?? 0,
                            'low_stock_threshold' => 3,
                        ]);
                    }

                    if (! empty($row['attributes'])) {
                        $this->persistAttributes($lockedShop, $product, $category?->id, $row['attributes']);
                    }

                    $updatedCount++;
                }
            }

            $summary = [
                'created' => $createdCount,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'categories_created' => $categoriesCreated,
            ];

            $session->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'summary' => $summary,
            ]);

            Log::info('Inventory import confirmed successfully', [
                'shop_id' => $lockedShop->id,
                'user_id' => $user?->id,
                'session_id' => $session->public_id,
                'summary' => $summary,
            ]);
        });

        foreach ($productIdsToResolveMedia as $productId) {
            try {
                ResolveProductCatalogMediaJob::dispatch($productId)->afterCommit();
            } catch (\Throwable $error) {
                // Products and session are already committed. Optional media must not turn success into a 500.
                Log::warning('Inventory import media resolution unavailable', ['shop_id' => $shop->id, 'product_id' => $productId, 'exception_type' => $error::class]);
            }
        }

        return $summary;
    }

    /**
     * Legacy persistence support for raw rows array.
     */
    public function persist(Shop $shop, array $inputRows, PlanLimitsService $limits, CatalogMediaService $catalogMedia, ?User $user = null): int
    {
        $rows = collect($inputRows)
            ->filter(fn ($row) => is_array($row) && ($row['valid'] ?? false))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Corrige las filas marcadas antes de importar.']);
        }

        foreach ($rows as $row) {
            if (! is_string($row['name'] ?? null) || trim($row['name']) === '' ||
                ! is_numeric($row['price'] ?? null) || (float) $row['price'] < 0 ||
                (($row['stock'] ?? null) !== null && (! is_numeric($row['stock']) || (int) $row['stock'] < 0))) {
                throw ValidationException::withMessages(['rows' => 'La vista previa de inventario ya no es válida. Vuelve a cargar el archivo.']);
            }
        }

        // Create a transient session and confirm it
        $session = InventoryImportSession::create([
            'shop_id' => $shop->id,
            'user_id' => $user?->id ?? $shop->user_id,
            'original_filename' => 'api-direct-import',
            'status' => 'previewed',
            'total_rows' => $rows->count(),
            'valid_rows' => $rows->count(),
            'invalid_rows' => 0,
            'rows_payload' => $rows->all(),
            'missing_categories' => [],
            'mapping' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        $summary = $this->confirmSession(
            $shop,
            $session,
            ['duplicate_strategy' => 'create', 'create_missing_categories' => true],
            $limits,
            $catalogMedia,
            $user
        );

        return (int) ($summary['created'] + $summary['updated']);
    }

    /**
     * Recognized fields dictionary.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'name' => 'Nombre del producto',
            'product_code' => 'Código / SKU',
            'barcode' => 'Código de barras',
            'brand' => 'Marca',
            'category' => 'Categoría',
            'description' => 'Descripción',
            'price' => 'Precio de venta',
            'cost_price' => 'Costo',
            'stock' => 'Stock',
            'notes' => 'Notas',
            'attributes' => 'Atributos',
        ];
    }

    /**
     * Robust monetary string parser.
     * Supports formats: 2500, 2500.50, 2500,50, 2,500, 2,500.50, 2.500,50, RD$ 2,500.50, $2500, etc.
     * Returns a 2-decimal string like "2500.50" or null if invalid/empty.
     */
    public static function parseMoney(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        // Normalize spaces and strip currency symbols
        $value = preg_replace('/[\x{00A0}\x{200B}\s]+/u', ' ', $value);
        $value = preg_replace('/^(RD\$|DOP|US\$|USD|\$|€)\s*/i', '', $value);
        $value = preg_replace('/\s*(RD\$|DOP|US\$|USD|\$|€)$/i', '', $value);
        $value = trim($value);

        if ($value === '' || ! preg_match('/^-?\d[\d., ]*$/', $value) || preg_match('/[.,]{2,}/', $value)) {
            return null;
        }

        if (str_contains($value, ' ')) {
            $value = str_replace(' ', '', $value);
        }

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($value, '.');
            $lastComma = strrpos($value, ',');

            if ($lastComma > $lastDot) {
                // e.g. 2.500,50 -> dot is thousand, comma is decimal
                $dec = substr($value, $lastComma + 1);
                if (! preg_match('/^\d{1,4}$/', $dec)) {
                    return null;
                }
                $th = substr($value, 0, $lastComma);
                $chunks = explode('.', $th);
                for ($i = 1; $i < count($chunks); $i++) {
                    if (strlen($chunks[$i]) !== 3) {
                        return null;
                    }
                }
                $canonical = str_replace('.', '', $th).'.'.$dec;
            } else {
                // e.g. 2,500.50 -> comma is thousand, dot is decimal
                $dec = substr($value, $lastDot + 1);
                if (! preg_match('/^\d{1,4}$/', $dec)) {
                    return null;
                }
                $th = substr($value, 0, $lastDot);
                $chunks = explode(',', $th);
                for ($i = 1; $i < count($chunks); $i++) {
                    if (strlen($chunks[$i]) !== 3) {
                        return null;
                    }
                }
                $canonical = str_replace(',', '', $th).'.'.$dec;
            }
        } elseif ($hasComma) {
            if (substr_count($value, ',') > 1) {
                // Multiple commas e.g. 1,000,000
                $chunks = explode(',', $value);
                for ($i = 1; $i < count($chunks); $i++) {
                    if (strlen($chunks[$i]) !== 3) {
                        return null;
                    }
                }
                $canonical = str_replace(',', '', $value);
            } else {
                $pos = strpos($value, ',');
                $b = substr($value, 0, $pos);
                $a = substr($value, $pos + 1);

                if (strlen($a) === 3 && strlen($b) >= 1 && strlen($b) <= 3 && ! str_starts_with($b, '0')) {
                    // e.g. 2,500 -> thousands separator
                    $canonical = $b.$a;
                } elseif (strlen($a) >= 1 && strlen($a) <= 4) {
                    // e.g. 2500,50 or 0,75 -> comma is decimal
                    $canonical = $b.'.'.$a;
                } else {
                    return null;
                }
            }
        } elseif ($hasDot) {
            if (substr_count($value, '.') > 1) {
                // Multiple dots e.g. 1.000.000
                $chunks = explode('.', $value);
                for ($i = 1; $i < count($chunks); $i++) {
                    if (strlen($chunks[$i]) !== 3) {
                        return null;
                    }
                }
                $canonical = str_replace('.', '', $value);
            } else {
                $pos = strpos($value, '.');
                $b = substr($value, 0, $pos);
                $a = substr($value, $pos + 1);

                if (strlen($a) === 3 && strlen($b) >= 1 && strlen($b) <= 3 && ! str_starts_with($b, '0')) {
                    // e.g. 2.500 -> thousands separator
                    $canonical = $b.$a;
                } elseif (strlen($a) >= 1 && strlen($a) <= 4) {
                    // e.g. 2500.50 or 0.75 -> dot is decimal
                    $canonical = $b.'.'.$a;
                } else {
                    return null;
                }
            }
        } else {
            $canonical = $value;
        }

        if (! is_numeric($canonical)) {
            return null;
        }

        // Round half-up using decimal arithmetic, never binary floating point.
        $rounded = bcadd($canonical, str_starts_with($canonical, '-') ? '-0.005' : '0.005', 2);

        return $rounded === '-0.00' ? '0.00' : $rounded;
    }

    /**
     * Stock quantity parser.
     */
    public static function parseStock(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $val = trim((string) $raw);
        if ($val === '') {
            return null;
        }
        if (! preg_match('/^\d+(?:[.,]0+)?$/D', $val)) {
            return -1; // Flag as negative / invalid
        }

        $integer = preg_split('/[.,]/', $val)[0];

        return strlen(ltrim($integer, '0')) > 9 || (int) $integer > 1000000000 ? -1 : (int) $integer;
    }

    private function normaliseRow(
        array $headers,
        array $mapping,
        array $row,
        int $line,
        $existingByBarcode,
        $existingBySku,
        $existingByName,
        $shopCategories,
        $globalCategories,
        array &$seenBarcodes,
        array &$seenSkus,
        array &$seenNames,
        array &$missingCategories,
    ): array {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        $value = fn (string $field): string => trim((string) ($data[$mapping[$field] ?? ''] ?? ''));

        $name = $value('name');
        $rawSku = $value('product_code');
        $rawBarcode = $value('barcode');
        $rawCategory = $value('category');

        $price = self::parseMoney($value('price'));
        $costPrice = self::parseMoney($value('cost_price'));
        $stock = self::parseStock($value('stock'));

        $catalogMedia = app(CatalogMediaService::class);
        $normalizedBarcode = $catalogMedia->normalizeBarcode($rawBarcode);

        $parsedAttributes = $this->attributes($value('attributes'));

        $errors = [];
        $warnings = array_merge([], $parsedAttributes['warnings']);

        if ($name === '') {
            $errors[] = 'Falta el nombre.';
        }
        foreach (['name' => [$name, 255], 'product_code' => [$rawSku, 100], 'barcode' => [$rawBarcode, 32], 'brand' => [$value('brand'), 120], 'category' => [$rawCategory, 255]] as $field => [$text, $maximum]) {
            if (mb_strlen($text) > $maximum) {
                $errors[] = "El campo {$field} supera {$maximum} caracteres.";
            }
        }
        if ($rawBarcode !== '' && ! preg_match('/^\d(?:[0-9\s-]*\d)?$/D', $rawBarcode)) {
            $errors[] = 'El código de barras debe contener dígitos, sin letras ni notación científica. Revisa el archivo original.';
        }
        if ($normalizedBarcode && ! in_array(strlen($normalizedBarcode), [8, 12, 13, 14], true)) {
            $warnings[] = 'La longitud del código no corresponde a EAN/UPC/GTIN estándar; revisa si es un código interno.';
        }

        if ($price === null) {
            $errors[] = 'El precio debe ser un número válido no negativo (ej. 2500, 2500.50, 2.500,50).';
        } elseif (str_contains($value('price'), '-')) {
            $errors[] = 'El precio no puede ser negativo.';
        } elseif (bccomp($price, '9999999999.99', 2) > 0) {
            $errors[] = 'El precio supera el máximo permitido (9999999999.99).';
        }

        if ($value('cost_price') !== '' && $costPrice === null) {
            $errors[] = 'El costo debe ser un número válido.';
        } elseif ($costPrice !== null && str_contains($value('cost_price'), '-')) {
            $errors[] = 'El costo no puede ser negativo.';
        } elseif ($costPrice !== null && bccomp($costPrice, '9999999999.99', 2) > 0) {
            $errors[] = 'El costo supera el máximo permitido (9999999999.99).';
        }

        if ($stock !== null && $stock < 0) {
            $errors[] = 'El stock debe ser un entero no negativo; no se permiten fracciones ni redondeos.';
        }

        // Duplicate checks within file
        $duplicateInternal = false;
        if ($normalizedBarcode) {
            if (isset($seenBarcodes[$normalizedBarcode])) {
                $errors[] = "Código de barras duplicado dentro del archivo (visto primero en fila {$seenBarcodes[$normalizedBarcode]}).";
                $duplicateInternal = true;
            } else {
                $seenBarcodes[$normalizedBarcode] = $line;
            }
        }

        if ($rawSku !== '') {
            $skuKey = Str::lower($rawSku);
            if (isset($seenSkus[$skuKey])) {
                $errors[] = "SKU duplicado dentro del archivo (visto primero en fila {$seenSkus[$skuKey]}).";
                $duplicateInternal = true;
            } else {
                $seenSkus[$skuKey] = $line;
            }
        }

        if (! $normalizedBarcode && $rawSku === '' && $name !== '') {
            $nameKey = Str::of($name)->squish()->ascii()->lower()->value();
            if (isset($seenNames[$nameKey])) {
                $warnings[] = "Producto con nombre idéntico repetido en el archivo (visto primero en fila {$seenNames[$nameKey]}).";
            } else {
                $seenNames[$nameKey] = $line;
            }
        }

        // Match against existing products in shop
        $matchType = 'none';
        $matchField = null;
        $matchedProduct = null;

        if ($normalizedBarcode && $existingByBarcode->has($normalizedBarcode)) {
            $matchedProduct = $existingByBarcode->get($normalizedBarcode);
            $matchType = 'existing';
            $matchField = 'barcode';
        } elseif ($rawSku !== '' && $existingBySku->has(Str::lower($rawSku))) {
            $matchedProduct = $existingBySku->get(Str::lower($rawSku));
            $matchType = 'existing';
            $matchField = 'product_code';
        } elseif ($name !== '') {
            $nameKey = Str::of($name)->squish()->ascii()->lower()->value();
            if ($existingByName->has($nameKey)) {
                $matchedProduct = $existingByName->get($nameKey);
                $matchType = 'possible_duplicate';
                $matchField = 'name';
                $warnings[] = "Posible duplicado: ya existe un producto con nombre similar ('{$matchedProduct->name}') en tu tienda.";
            }
        }

        // Check category recognition
        $categoryStatus = 'none';
        if ($rawCategory !== '') {
            $catKey = Str::of($rawCategory)->squish()->ascii()->lower()->value();
            if ($shopCategories->has($catKey)) {
                $categoryStatus = 'shop_category';
            } elseif ($globalCategories->has($catKey)) {
                $categoryStatus = 'global_category';
            } else {
                $categoryStatus = 'missing';
                $missingCategories[$rawCategory] = true;
                $warnings[] = "La categoría '{$rawCategory}' no existe en la tienda.";
            }
        }

        // Status classification
        $status = 'new';
        $defaultAction = 'create';
        if (! empty($errors)) {
            $status = 'invalid';
            $defaultAction = 'skip';
        } elseif ($matchType === 'existing') {
            $status = 'existing';
            $defaultAction = 'update';
        } elseif ($matchType === 'possible_duplicate') {
            $status = 'possible_duplicate';
            $defaultAction = 'create';
        }

        return [
            'line' => $line,
            'name' => $name,
            'product_code' => $rawSku !== '' ? $rawSku : null,
            'barcode' => $rawBarcode !== '' ? $rawBarcode : null,
            'normalized_barcode' => $normalizedBarcode,
            'brand' => $value('brand') ?: null,
            'category' => $rawCategory ?: null,
            'category_status' => $categoryStatus,
            'description' => $value('description') ?: null,
            'notes' => $value('notes') ?: null,
            'price' => $price,
            'cost_price' => $costPrice,
            'stock' => $stock,
            'attributes' => $parsedAttributes['items'],
            'status' => $status,
            'match_type' => $matchType,
            'match_field' => $matchField,
            'matched_product_id' => $matchedProduct?->id,
            'matched_product_name' => $matchedProduct?->name,
            'errors' => $errors,
            'warnings' => $warnings,
            'valid' => empty($errors),
            'action' => $defaultAction,
        ];
    }

    private function attributes(string $value): array
    {
        if ($value === '') {
            return ['items' => [], 'warnings' => []];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $items = collect($decoded)
                ->map(fn ($item, $name) => ['name' => (string) $name, 'value' => (string) $item])
                ->values()
                ->all();

            return ['items' => $items, 'warnings' => []];
        }

        $seen = [];
        $items = [];
        $warnings = [];

        $rawPairs = preg_split('/[;|\n]+/', $value) ?: [];
        foreach ($rawPairs as $item) {
            [$name, $val] = array_pad(explode('=', $item, 2), 2, '');
            $name = trim($name);
            $val = trim($val);

            if ($name === '' || $val === '') {
                continue;
            }

            $nameKey = Str::lower($name);
            if (isset($seen[$nameKey])) {
                $warnings[] = "Atributo '{$name}' duplicado en la misma fila (se conserva el primer valor).";

                continue;
            }
            $seen[$nameKey] = true;
            $items[] = ['name' => $name, 'value' => $val];
        }

        return ['items' => $items, 'warnings' => $warnings];
    }

    private function persistAttributes(Shop $shop, Product $product, ?int $categoryId, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $val = trim((string) ($attribute['value'] ?? ''));
            if ($name === '' || $val === '') {
                continue;
            }

            $definition = AttributeDefinition::firstOrCreate([
                'shop_id' => $shop->id,
                'shop_category_id' => $categoryId,
                'slug' => Str::slug($name) ?: 'attr',
            ], ['name' => $name, 'filterable' => true]);

            $product->attributeValues()->updateOrCreate([
                'attribute_definition_id' => $definition->id,
            ], [
                'value' => $val,
            ]);
        }
    }

    private function detectMojibake(array $headers, array $rows): bool
    {
        $patterns = ['Ã±', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Â', 'ï»¿'];
        $sample = implode(' ', $headers);
        foreach (array_slice($rows, 0, 15) as $row) {
            $sample .= ' '.implode(' ', array_map('strval', $row));
        }

        foreach ($patterns as $pattern) {
            if (str_contains($sample, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function uniqueSlug(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        $suffix = 2;
        while ($shop->products()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
