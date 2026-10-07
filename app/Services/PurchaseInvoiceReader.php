<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryImport\ColumnDetector;
use App\Services\InventoryImport\WorkbookReader;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reads supplier invoices into a review-only purchase draft.
 *
 * This service deliberately does not create products, documents, movements or
 * lots. The reviewed lines are posted through the normal purchase endpoint,
 * which remains the only place that can receive inventory.
 */
class PurchaseInvoiceReader
{
    public function __construct(
        private WorkbookReader $reader,
        private ColumnDetector $detector,
        private CatalogMediaService $catalogMedia,
    ) {}

    /**
     * @return array{upload_token: string, file: array<string, mixed>, rows: array<int, array<string, mixed>>, mapping: array<string, string>, warnings: array<int, string>, counts: array<string, int>}
     */
    public function preview(UploadedFile $file, Shop $shop, User $user): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                'file' => 'La lectura automática de fotos todavía requiere OCR. Sube un PDF con texto seleccionable o un Excel/CSV; la foto no se guardará ni modificará tu inventario.',
            ]);
        }

        $book = $this->reader->read($file);
        $selected = $this->selectSheet($book['sheets']);
        $header = $this->detector->header($selected['rows']);
        if ($header['needs_selection']) {
            throw ValidationException::withMessages([
                'file' => 'No pude identificar con seguridad la fila de encabezados. Usa un Excel/CSV con columnas Producto, Cantidad y Costo unitario.',
            ]);
        }

        $filteredRows = array_values(array_filter(
            array_slice($selected['rows'], $header['row']),
            fn (array $row): bool => count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) > 0,
        ));
        if ($filteredRows === []) {
            throw ValidationException::withMessages(['file' => 'La factura no contiene líneas de productos legibles.']);
        }
        if (count($filteredRows) > 5000) {
            throw ValidationException::withMessages(['file' => 'La factura supera 5000 líneas. Divide el archivo antes de leerlo.']);
        }

        $detection = $this->detector->mapping($header['values'], $filteredRows);
        $mapping = $detection['mapping'];
        $nameIndex = $this->columnIndex($mapping['name'], $detection['headers']);
        $codeIndex = $this->columnIndex($mapping['product_code'], $detection['headers']);
        $barcodeIndex = $this->columnIndex($mapping['barcode'], $detection['headers']);
        $quantityIndex = $this->columnIndex($mapping['stock'], $detection['headers']);
        $costIndex = $this->columnIndex($mapping['cost_price'], $detection['headers']);
        $priceIndex = $this->columnIndex($mapping['price'], $detection['headers']);

        if ($nameIndex === null && $codeIndex === null && $barcodeIndex === null) {
            throw ValidationException::withMessages(['file' => 'La factura debe incluir una columna de producto, SKU o código de barras.']);
        }
        if ($costIndex === null && $priceIndex === null) {
            throw ValidationException::withMessages(['file' => 'La factura debe incluir una columna de costo unitario o precio de compra.']);
        }

        $products = $shop->products()
            ->get(['id', 'public_id', 'name', 'product_code', 'barcode'])
            ->values();
        $byBarcode = $products->filter(fn (Product $product): bool => filled($product->barcode))
            ->mapWithKeys(fn (Product $product): array => [(string) $this->catalogMedia->normalizeBarcode($product->barcode) => $product])
            ->filter(fn (Product $product, string $key): bool => $key !== '');
        $byCode = $products->filter(fn (Product $product): bool => filled($product->product_code))
            ->keyBy(fn (Product $product): string => Str::lower(trim((string) $product->product_code)));
        $byName = $products->keyBy(fn (Product $product): string => $this->nameKey($product->name));

        $warnings = array_values(array_unique(array_merge(
            $book['warnings'] ?? [],
            $detection['warnings'] ?? [],
            $quantityIndex === null ? ['No se detectó una columna de cantidad; se usó 1 en cada línea. Revisa la factura antes de confirmar.'] : [],
            $costIndex === null && $priceIndex !== null ? ['No se detectó una columna de costo; se usó la columna de precio como costo unitario. Revisa el mapeo antes de recibir.'] : [],
        )));
        $rows = [];

        foreach ($filteredRows as $position => $rawRow) {
            $line = $position + $header['row'] + 1;
            $name = $this->value($rawRow, $nameIndex);
            $code = $this->value($rawRow, $codeIndex);
            $barcode = $this->value($rawRow, $barcodeIndex);
            $quantityRaw = $this->value($rawRow, $quantityIndex);
            $costRaw = $this->value($rawRow, $costIndex ?? $priceIndex);
            $quantity = $quantityIndex === null ? 1 : $this->parseQuantity($quantityRaw);
            $unitCost = InventoryImportService::parseMoney($costRaw);
            $errors = [];
            $rowWarnings = [];

            if ($name === '' && $code === '' && $barcode === '') {
                $errors[] = 'La línea no tiene producto, SKU ni código de barras.';
            }
            if ($quantity === null || $quantity < 1) {
                $errors[] = 'La cantidad debe ser un entero mayor que cero.';
            }
            if ($unitCost === null || str_contains($costRaw, '-')) {
                $errors[] = 'El costo unitario no es válido.';
            }

            [$matched, $matchType] = $this->matchProduct($name, $code, $barcode, $byBarcode, $byCode, $byName);
            if (! $matched && $errors === []) {
                $errors[] = 'No encontramos este producto en la tienda; selecciónalo manualmente.';
            }
            if ($matched && $name !== '' && $this->nameKey($matched->name) !== $this->nameKey($name)) {
                $rowWarnings[] = "Factura: {$name}. Producto seleccionado: {$matched->name}.";
            }

            $costCents = $unitCost !== null ? Money::toCents($unitCost) : 0;
            $rows[] = [
                'line' => $line,
                'source_name' => $name,
                'source_code' => $code !== '' ? $code : ($barcode !== '' ? $barcode : null),
                'product_id' => $matched?->public_id,
                'product_name' => $matched?->name ?: ($name !== '' ? $name : 'Producto sin nombre'),
                'match_type' => $matchType,
                'match_label' => $this->matchLabel($matchType),
                'quantity' => $quantity ?? 0,
                'unit_cost' => $unitCost,
                'line_total' => $quantity !== null && $unitCost !== null ? Money::toDecimal($costCents * $quantity) : '0.00',
                'errors' => $errors,
                'warnings' => $rowWarnings,
                'valid' => $errors === [],
            ];
        }

        $token = (string) Str::uuid();
        Cache::put('purchase-invoice-upload:'.$token, [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'filename' => basename($file->getClientOriginalName()),
            'rows' => $rows,
            'mapping' => $mapping,
        ], now()->addHours(2));

        $validRows = collect($rows)->where('valid', true)->count();

        return [
            'upload_token' => $token,
            'file' => ['name' => basename($file->getClientOriginalName()), 'type' => $book['type']],
            'rows' => $rows,
            'mapping' => $mapping,
            'warnings' => $warnings,
            'counts' => ['total' => count($rows), 'valid' => $validRows, 'needs_review' => count($rows) - $validRows, 'matched' => collect($rows)->whereNotNull('product_id')->count()],
        ];
    }

    /** @param array<int, array<string, mixed>> $sheets */
    private function selectSheet(array $sheets): array
    {
        $ranked = collect($sheets)->map(function (array $sheet): array {
            $header = $this->detector->header($sheet['rows']);

            return ['sheet' => $sheet, 'confidence' => $header['confidence'], 'data_rows' => count($sheet['rows']) - $header['row']];
        })->sortByDesc(fn (array $item): string => sprintf('%0.4f-%08d', $item['confidence'], $item['data_rows']))->first();

        if (! $ranked) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene hojas legibles.']);
        }

        return $ranked['sheet'];
    }

    private function columnIndex(string $source, array $headers): ?int
    {
        if ($source === '') {
            return null;
        }

        $index = array_search($source, $headers, true);

        return $index === false ? null : $index;
    }

    private function value(array $row, ?int $index): string
    {
        return $index === null ? '' : trim((string) ($row[$index] ?? ''));
    }

    private function parseQuantity(string $value): ?int
    {
        $quantity = InventoryImportService::parseStock($value);

        return $quantity !== null && $quantity >= 0 ? $quantity : null;
    }

    /** @return array{0: Product|null, 1: string} */
    private function matchProduct(string $name, string $code, string $barcode, $byBarcode, $byCode, $byName): array
    {
        $normalizedBarcode = $this->catalogMedia->normalizeBarcode($barcode);
        if ($normalizedBarcode && $byBarcode->has($normalizedBarcode)) {
            return [$byBarcode->get($normalizedBarcode), 'barcode'];
        }
        if ($code !== '' && $byCode->has(Str::lower(trim($code)))) {
            return [$byCode->get(Str::lower(trim($code))), 'product_code'];
        }
        if ($name !== '' && $byName->has($this->nameKey($name))) {
            return [$byName->get($this->nameKey($name)), 'name'];
        }

        return [null, 'none'];
    }

    private function nameKey(string $name): string
    {
        return Str::of($name)->squish()->ascii()->lower()->value();
    }

    private function matchLabel(string $type): string
    {
        return match ($type) {
            'barcode' => 'Encontrado por código de barras',
            'product_code' => 'Encontrado por SKU',
            'name' => 'Encontrado por nombre',
            default => 'Requiere selección',
        };
    }
}
