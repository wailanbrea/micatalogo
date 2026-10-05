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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class InventoryImportService
{
    /**
     * Preview inventory file, normalize columns, detect duplicates, and create a server-side session.
     *
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $file, array $mapping = [], ?Shop $shop = null, ?User $user = null): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rawRows = match ($extension) {
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw new RuntimeException('El archivo debe ser CSV o XLSX.'),
        };

        if ($rawRows === []) {
            return [
                'headers' => [],
                'mapping' => [],
                'rows' => [],
                'session_id' => null,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'missing_categories' => [],
                'mojibake_warning' => false,
            ];
        }

        $headers = array_map(fn ($header) => $this->normaliseHeader((string) $header), array_shift($rawRows));
        $mapping = $this->normaliseMapping($headers, $mapping);

        $filteredRows = collect($rawRows)
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty())
            ->values()
            ->all();

        $mojibakeWarning = $this->detectMojibake($headers, $filteredRows);

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
            $line = $index + 2;
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
            $rows[] = $row;
        }

        $validRowsCount = collect($rows)->where('valid', true)->count();
        $invalidRowsCount = collect($rows)->where('valid', false)->count();

        // Create a server-side import session if shop is present
        $session = null;
        if ($shop) {
            $session = InventoryImportSession::create([
                'shop_id' => $shop->id,
                'user_id' => $user?->id,
                'original_filename' => $file->getClientOriginalName(),
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

            // Determine effective action for each row
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
            ResolveProductCatalogMediaJob::dispatch($productId)->afterCommit();
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

        return number_format((float) $canonical, 2, '.', '');
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
        if (! is_numeric($val)) {
            return -1; // Flag as negative / invalid
        }

        return (int) round((float) $val);
    }

    private function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('No se pudo leer el archivo CSV.');
        }

        // Clean UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false || empty($lines)) {
            return [];
        }

        // Detect delimiter based on first non-empty lines
        $firstLine = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $firstLine = $line;
                break;
            }
        }
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene habilitado el lector XLSX. Usa CSV mientras se habilita ZipArchive.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        $sharedStrings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $shared = simplexml_load_string($xml);
            foreach ($shared->si as $item) {
                $sharedStrings[] = trim(implode('', $item->xpath('.//t') ?: []));
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('El XLSX no contiene una primera hoja válida.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $matches);
                $column = $this->columnNumber($matches[0] ?? 'A');
                $value = (string) ($cell->v ?? '');
                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = trim(implode('', $cell->is->xpath('.//t') ?: []));
                }
                $cells[$column] = trim($value);
            }
            $rows[] = $cells === [] ? [] : array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
        }

        return $rows;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = ($number * 26) + (ord($letter) - 64);
        }

        return max(0, $number - 1);
    }

    private function normaliseHeader(string $header): string
    {
        return Str::of($header)
            ->replace("\xEF\xBB\xBF", '')
            ->ascii()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->value();
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

        if ($price === null) {
            $errors[] = 'El precio debe ser un número válido no negativo (ej. 2500, 2500.50, 2.500,50).';
        } elseif ((float) $price < 0) {
            $errors[] = 'El precio no puede ser negativo.';
        }

        if ($costPrice !== null && (float) $costPrice < 0) {
            $errors[] = 'El costo no puede ser negativo.';
        }

        if ($stock !== null && $stock < 0) {
            $errors[] = 'El stock no puede ser negativo.';
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

    private function normaliseMapping(array $headers, array $mapping): array
    {
        $synonyms = [
            'name' => ['nombre', 'producto', 'name', 'articulo', 'descripcion producto', 'item'],
            'product_code' => ['codigo', 'sku', 'product code', 'code', 'referencia', 'ref', 'item code'],
            'barcode' => ['barcode', 'ean', 'gtin', 'upc', 'codigo de barras', 'codigo barras'],
            'brand' => ['marca', 'brand', 'fabricante'],
            'category' => ['categoria', 'categoria interna', 'category', 'familia', 'departamento', 'seccion'],
            'description' => ['descripcion', 'description', 'detalle'],
            'price' => ['precio', 'price', 'precio venta', 'venta', 'p venta', 'precio unitario', 'pvp'],
            'cost_price' => ['costo', 'precio costo', 'cost', 'p costo', 'precio compra'],
            'stock' => ['stock', 'existencias', 'cantidad', 'quantity', 'inventario', 'disponible', 'unidades'],
            'notes' => ['notas', 'observaciones', 'notes'],
            'attributes' => ['atributos', 'caracteristicas', 'attributes', 'variantes'],
        ];
        $validHeaders = array_flip($headers);

        return collect($this->fields())->mapWithKeys(function (string $label, string $field) use ($mapping, $synonyms, $validHeaders): array {
            $selected = $this->normaliseHeader((string) ($mapping[$field] ?? ''));
            if ($selected !== '' && isset($validHeaders[$selected])) {
                return [$field => $selected];
            }

            foreach ($synonyms[$field] as $candidate) {
                if (isset($validHeaders[$candidate])) {
                    return [$field => $candidate];
                }
            }

            return [$field => ''];
        })->all();
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
