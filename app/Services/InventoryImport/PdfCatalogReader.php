<?php

namespace App\Services\InventoryImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\XObject\Image as PdfImage;
use Throwable;

/**
 * Reads text-based catalogue PDFs into the same row shape used by Excel/CSV.
 *
 * PDFs do not carry a reliable table model. We therefore prefer delimited
 * tables (semicolon, tab or pipe) and fall back to the common catalogue form
 * "name price stock". Scanned/image-only PDFs are rejected with a useful
 * message instead of silently creating incorrect products.
 */
class PdfCatalogReader
{
    public const MAX_PAGES = 20;

    private const MAX_EMBEDDED_IMAGE_BYTES = 2_000_000;

    private const MAX_TOTAL_IMAGE_BYTES = 5_000_000;

    public function read(UploadedFile $file): array
    {
        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->fail('El archivo supera 10 MB.');
        }

        try {
            $document = (new Parser)->parseFile($file->getRealPath());
            $pages = $document->getPages();
        } catch (Throwable $exception) {
            report($exception);
            $this->fail('El PDF no se pudo leer. Usa un PDF válido con texto seleccionable.');
        }

        if (count($pages) > self::MAX_PAGES) {
            $this->fail('El PDF supera 20 páginas. Divide el catálogo en archivos más pequeños.');
        }

        $sheets = [];
        $warnings = [];
        $totalRows = 0;
        $totalImageBytes = 0;

        foreach ($pages as $pageIndex => $page) {
            $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $page->getText()) ?? '');
            $lines = $this->lines($text);
            if ($lines === []) {
                $warnings[] = 'La página '.($pageIndex + 1).' no contiene texto seleccionable; se omitió.';

                continue;
            }

            [$rows, $pageWarnings] = $this->rowsFromLines($lines);
            $warnings = array_merge($warnings, array_map(fn (string $warning): string => 'Página '.($pageIndex + 1).': '.$warning, $pageWarnings));

            if ($rows === []) {
                $warnings[] = 'La página '.($pageIndex + 1).' no contiene filas con nombre y precio reconocibles; se omitió.';

                continue;
            }

            $embeddedImages = [];
            foreach ($this->embeddedImages($page) as $image) {
                $size = strlen($image['bytes']);
                if ($size > self::MAX_EMBEDDED_IMAGE_BYTES || $totalImageBytes + $size > self::MAX_TOTAL_IMAGE_BYTES) {
                    $warnings[] = 'Se omitió una imagen embebida por superar el límite seguro de imágenes del archivo.';

                    continue;
                }
                $totalImageBytes += $size;
                $embeddedImages[] = $image;
            }

            foreach ($rows as $rowIndex => &$row) {
                if (isset($embeddedImages[$rowIndex])) {
                    $row['_embedded_image'] = $embeddedImages[$rowIndex];
                }
            }
            unset($row);

            $header = isset($rows[0]['_headers'])
                ? $rows[0]['_headers']
                : ['Nombre del producto', 'Precio de venta', 'Stock', 'Imagen'];
            $dataRows = array_map(function (array $row): array {
                $embedded = $row['_embedded_image'] ?? null;
                unset($row['_headers'], $row['_header'], $row['_embedded_image']);
                if ($embedded) {
                    $row[] = '';
                }

                return $row;
            }, $rows);

            // When a delimited header was found, it is carried separately by
            // rowsFromLines; otherwise use the inferred catalogue columns.
            if (collect($rows)->contains(fn (array $row): bool => ! empty($row['_embedded_image']))
                && ! collect($header)->contains(fn ($value): bool => str_contains(Str::lower(Str::ascii((string) $value)), 'imagen'))
            ) {
                $header[] = 'Imagen';
            }

            $imageRows = [];
            foreach ($rows as $rowIndex => $row) {
                if (! empty($row['_embedded_image'])) {
                    $imageRows[$rowIndex + 2] = [$row['_embedded_image']];
                }
            }

            $sheetRows = [$header, ...$dataRows];
            $totalRows += count($dataRows);
            $sheets[] = [
                'name' => 'Página '.($pageIndex + 1),
                'index' => $pageIndex,
                'rows' => $sheetRows,
                'image_rows' => $imageRows,
                'unformatted_numeric_columns' => [],
            ];
        }

        if ($sheets === [] || $totalRows === 0) {
            $this->fail('El PDF no contiene una tabla de productos legible. Usa un PDF con texto seleccionable y columnas de nombre y precio, o conviértelo a Excel.');
        }

        // A catalogue PDF is one logical import even when it spans several
        // pages. The existing importer intentionally selects one workbook
        // sheet, so combine all PDF pages into that single sheet here.
        $firstHeader = $sheets[0]['rows'][0];
        $combinedRows = [$firstHeader];
        $combinedImageRows = [];
        foreach ($sheets as $sheet) {
            $dataRows = array_slice($sheet['rows'], 1);
            foreach ($sheet['image_rows'] as $oldRow => $images) {
                $combinedImageRows[count($combinedRows) + ((int) $oldRow - 1)] = $images;
            }
            $combinedRows = [...$combinedRows, ...$dataRows];
        }

        return [
            'type' => 'pdf',
            'sheets' => [[
                'name' => 'Catálogo PDF',
                'index' => 0,
                'rows' => $combinedRows,
                'image_rows' => $combinedImageRows,
                'unformatted_numeric_columns' => [],
            ]],
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @return array<int, string> */
    private function lines(string $text): array
    {
        return array_values(array_filter(array_map(function (string $line): string {
            return trim(preg_replace('/\s+/u', ' ', $line) ?? '');
        }, preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{0: array<int, array<int|string, mixed>>, 1: array<int, string>}
     */
    private function rowsFromLines(array $lines): array
    {
        $warnings = [];
        $delimiter = $this->delimiter($lines);
        if ($delimiter !== null) {
            $parsed = array_map(fn (string $line): array => str_getcsv($line, $delimiter, '"', '\\'), $lines);
            $headerIndex = $this->headerIndex($parsed);
            if ($headerIndex !== null) {
                $headers = array_map(fn ($value): string => trim((string) $value), $parsed[$headerIndex]);
                $rows = [];
                foreach (array_slice($parsed, $headerIndex + 1) as $row) {
                    if (count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) < 2) {
                        continue;
                    }
                    $rows[] = [...array_map(fn ($value): string => trim((string) $value), $row), '_headers' => $headers];
                }

                if ($rows !== []) {
                    return [$rows, $warnings];
                }
            }
        }

        $rows = [];
        $headerDetected = false;
        foreach ($lines as $line) {
            $normalized = Str::lower(Str::ascii($line));
            if (! $headerDetected && str_contains($normalized, 'nombre') && str_contains($normalized, 'precio')) {
                $headerDetected = true;

                continue;
            }

            $parsed = $this->inferSimpleRow($line);
            if ($parsed === null) {
                $warnings[] = 'Se omitió una línea que no tiene un precio reconocible: '.Str::limit($line, 100);

                continue;
            }
            $rows[] = $parsed;
        }

        if ($rows !== []) {
            $warnings[] = 'El PDF no expone celdas de tabla. Se infirieron las columnas Nombre, Precio de venta y Stock; revisa la vista previa.';
        }

        return [$rows, $warnings];
    }

    /** @param array<int, string> $lines */
    private function delimiter(array $lines): ?string
    {
        foreach ([';', "\t", '|'] as $delimiter) {
            $matches = count(array_filter($lines, fn (string $line): bool => substr_count($line, $delimiter) >= 1));
            if ($matches >= 2) {
                return $delimiter;
            }
        }

        return null;
    }

    /** @param array<int, array<int, string>> $rows */
    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $text = Str::lower(Str::ascii(implode(' ', $row)));
            if (str_contains($text, 'nombre') && str_contains($text, 'precio')) {
                return $index;
            }
        }

        return null;
    }

    /** @return array<int|string, mixed>|null */
    private function inferSimpleRow(string $line): ?array
    {
        $imageUrl = null;
        if (preg_match('/https:\/\/[^\s]+/i', $line, $match)) {
            $imageUrl = rtrim($match[0], '.,;)');
            $line = trim(str_replace($match[0], '', $line));
        }

        $pattern = '/^(?<name>.*?)\s*(?<price>(?:(?:RD\$|DOP|US\$|USD|\$|€)\s*)?\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{1,2})?|(?:(?:RD\$|DOP|US\$|USD|\$|€)\s*)?\d+(?:[.,]\d{1,2})?)\s*(?<stock>\d{1,9})?$/u';
        if (! preg_match($pattern, $line, $match)) {
            return null;
        }

        $name = trim((string) ($match['name'] ?? ''));
        $price = trim((string) ($match['price'] ?? ''));
        if ($name === '' || $price === '') {
            return null;
        }

        $row = [$name, $price];
        if (($match['stock'] ?? '') !== '') {
            $row[] = $match['stock'];
        }
        if ($imageUrl !== null) {
            $row[] = $imageUrl;
        }

        return $row;
    }

    /** @return array<int, array{bytes: string, mime_type: string}> */
    private function embeddedImages($page): array
    {
        $images = [];
        foreach ($page->getXObjects() as $xobject) {
            if (! $xobject instanceof PdfImage) {
                continue;
            }
            $bytes = (string) ($xobject->getContent() ?? '');
            $info = @getimagesizefromstring($bytes);
            $mime = (string) ($info['mime'] ?? '');
            if ($bytes === '' || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
                continue;
            }
            $images[] = ['bytes' => $bytes, 'mime_type' => $mime];
        }

        return $images;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
