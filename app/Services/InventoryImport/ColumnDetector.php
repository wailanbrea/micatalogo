<?php

namespace App\Services\InventoryImport;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Deterministic detection; no customer data leaves the application. */
class ColumnDetector
{
    public const ALIASES = [
        'name' => ['nombre', 'nombre del producto', 'producto', 'name', 'product name', 'item name', 'articulo', 'descripcion producto', 'item'],
        'product_code' => ['codigo', 'codigo del producto', 'codigo producto', 'cod prod', 'sku', 'product code', 'code', 'referencia', 'ref', 'item code'],
        'barcode' => ['barcode', 'bar code', 'ean', 'ean13', 'gtin', 'upc', 'codigo de barras', 'codigo barras'],
        'category' => ['categoria', 'categoria interna', 'category', 'familia', 'departamento', 'seccion'],
        'brand' => ['marca', 'brand', 'fabricante', 'manufacturer'],
        'price' => ['precio', 'price', 'precio venta', 'precio de venta', 'venta', 'p venta', 'precio unitario', 'unit price', 'selling price', 'sale price', 'pvp'],
        'cost_price' => ['costo', 'coste', 'costo unitario', 'precio costo', 'cost', 'unit cost', 'purchase price', 'p costo', 'precio compra'],
        'stock' => ['stock', 'existencias', 'cantidad', 'cant', 'qty', 'quantity', 'inventario', 'disponible', 'unidades'],
        'notes' => ['notas', 'observaciones', 'notes', 'remarks'],
        'description' => ['descripcion', 'description', 'detalle'],
        'attributes' => ['atributos', 'caracteristicas', 'attributes', 'variantes'],
        'image' => ['imagen', 'imagen del producto', 'foto', 'fotografia', 'image', 'product image', 'photo', 'url imagen', 'imagen url', 'image url', 'foto url'],
    ];

    public function normalize(string $header): string
    {
        $header = str_replace(["\xEF\xBB\xBF", 'ï»¿'], '', $header);
        if (preg_match('/[ÃÂ]/u', $header)) {
            $repaired = @iconv('UTF-8', 'Windows-1252', $header);
            if ($repaired !== false && mb_check_encoding($repaired, 'UTF-8')) {
                $header = $repaired;
            }
        }
        $header = \Normalizer::normalize($header, \Normalizer::FORM_KC) ?: $header;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($header))) ?? '');
    }

    public function header(array $rows, ?int $override = null): array
    {
        $candidates = [];
        foreach (array_slice($rows, 0, (int) config('inventory-import.header_scan_rows', 50), true) as $index => $row) {
            $keys = array_map(fn ($v) => $this->normalize((string) $v), $row);
            $recognized = [];
            foreach (self::ALIASES as $field => $aliases) {
                if (array_intersect($keys, $aliases)) {
                    $recognized[] = $field;
                }
            }
            $nonempty = array_filter($keys, fn ($v) => $v !== '');
            $score = count($recognized) * .12 + (in_array('name', $recognized) ? .30 : 0)
                + (array_intersect($recognized, ['price', 'cost_price', 'stock']) ? .20 : 0);
            if (count($nonempty) < 2) {
                $score = 0;
            }
            if (count(array_unique($nonempty)) < count($nonempty)) {
                $score *= .75;
            }
            $candidates[] = ['row' => $index + 1, 'confidence' => min(1, round($score, 2)), 'values' => $row];
        }
        usort($candidates, fn ($a, $b) => ($b['confidence'] <=> $a['confidence']) ?: ($a['row'] <=> $b['row']));
        $best = $candidates[0] ?? ['row' => 1, 'confidence' => 0, 'values' => []];
        if ($override !== null) {
            if (! isset($rows[$override - 1]) || count(array_filter($rows[$override - 1], fn ($v) => trim((string) $v) !== '')) < 2) {
                throw ValidationException::withMessages(['header_row' => 'Selecciona una fila con al menos dos encabezados.']);
            }
            $best = ['row' => $override, 'confidence' => 1, 'values' => $rows[$override - 1]];
        }

        return $best + ['candidates' => array_slice($candidates, 0, 10), 'needs_selection' => $best['confidence'] < .60];
    }

    public function mapping(array $headers, array $rows, array $prior = [], bool $manual = false): array
    {
        $mapping = array_fill_keys(array_keys(self::ALIASES), '');
        $details = $ignored = $warnings = [];
        $normalized = array_map(fn ($h) => $this->normalize((string) $h), $headers);
        // Duplicate display labels still have distinct, stable source-column keys.
        $keys = [];
        foreach ($normalized as $i => $key) {
            $keys[] = $key !== '' && count(array_keys($normalized, $key)) === 1 ? $key : 'column '.($i + 1);
        }
        foreach ($headers as $index => $original) {
            $key = $normalized[$index];
            $samples = array_values(array_filter(array_column(array_slice($rows, 0, 50), $index), fn ($v) => trim((string) $v) !== ''));
            $field = null;
            if (! preg_match('/(?:inventario|inventory|total|subtotal|valor|value)/', $key) || $key === 'inventario') {
                foreach (self::ALIASES as $candidate => $aliases) {
                    if (in_array($key, $aliases, true)) {
                        $field = $candidate;
                        break;
                    }
                }
            }
            $reason = 'Encabezado reconocido';
            $confidence = $field ? .95 : 0;
            if ($field === 'product_code' && ! in_array($key, ['sku', 'referencia', 'ref'], true) && $samples) {
                $barcodes = count(array_filter($samples, fn ($v) => preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/D', (string) $v)));
                if ($barcodes / count($samples) >= .80) {
                    $field = 'barcode';
                    $confidence = .90;
                    $reason = 'La muestra contiene códigos EAN/UPC/GTIN';
                }
            }
            if ($field && $mapping[$field] === '') {
                $mapping[$field] = $keys[$index];
                $details[$field] = ['field' => $field, 'source_column' => $keys[$index], 'original_header' => (string) $original, 'confidence' => $confidence, 'reason' => $reason, 'examples' => array_slice($samples, 0, 3)];
            } else {
                $ignored[] = ['source_column' => $keys[$index], 'original_header' => (string) $original, 'reason' => $field ? 'Otra columna ya corresponde a este campo' : 'Columna desconocida o calculada', 'examples' => array_slice($samples, 0, 3)];
                if ($field) {
                    $warnings[] = "Más de una columna corresponde a {$field}; revisa la selección.";
                }
            }
        }
        foreach ($prior as $field => $source) {
            if (! array_key_exists($field, $mapping)) {
                continue;
            }
            $source = $this->normalize((string) $source);
            if ($manual && $source === '') {
                $mapping[$field] = '';
                unset($details[$field]);

                continue;
            }
            $index = array_search($source, $keys, true);
            // Saved mapping is only a hint for unidentified fields, never overrides fresh detection.
            if ($index !== false && ($manual || $mapping[$field] === '') && ($manual || ! in_array($source, $mapping, true))) {
                $mapping[$field] = $source;
                $details[$field] = ['field' => $field, 'source_column' => $source, 'original_header' => $headers[$index], 'confidence' => $manual ? 1 : .70, 'reason' => $manual ? 'Seleccionado por el usuario' : 'Formato guardado', 'examples' => array_slice(array_column($rows, $index), 0, 3)];
            }
        }
        $used = array_filter($mapping);
        if (count($used) !== count(array_unique($used))) {
            throw ValidationException::withMessages(['mapping' => 'Una columna no puede alimentar dos campos. Revisa precio, costo y códigos.']);
        }
        $ignored = [];
        foreach ($headers as $index => $original) {
            if (! in_array($keys[$index], $mapping, true)) {
                $ignored[] = ['source_column' => $keys[$index], 'original_header' => (string) $original, 'reason' => 'No seleccionado; desconocido, calculado o ignorado por el usuario', 'examples' => array_slice(array_column($rows, $index), 0, 3)];
            }
        }
        foreach (['name' => 'nombre', 'price' => 'precio de venta'] as $field => $label) {
            if ($mapping[$field] === '') {
                $warnings[] = "Selecciona manualmente la columna de {$label}.";
            }
        }

        return ['headers' => $keys, 'original_headers' => $headers, 'mapping' => $mapping, 'mapping_confidence' => $details, 'ignored_columns' => $ignored, 'warnings' => $warnings];
    }
}
