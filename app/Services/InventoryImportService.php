<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class InventoryImportService
{
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw new RuntimeException('El archivo debe ser CSV o XLSX.'),
        };

        if ($rows === []) {
            return [];
        }

        $headers = array_map(fn ($header) => $this->normaliseHeader((string) $header), array_shift($rows));

        return collect($rows)
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty())
            ->values()
            ->map(fn (array $row, int $index) => $this->normaliseRow($headers, $row, $index + 2))
            ->all();
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('No se pudo leer el archivo CSV.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
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

    private function normaliseRow(array $headers, array $row, int $line): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        $name = $this->first($data, ['nombre', 'producto', 'name']);
        $priceValue = $this->first($data, ['precio', 'price', 'precio venta', 'venta']);
        $stockValue = $this->first($data, ['stock', 'existencias', 'cantidad', 'quantity', 'inventario']);
        $rowData = [
            'line' => $line,
            'name' => $name,
            'product_code' => $this->first($data, ['codigo', 'sku', 'product code', 'code']) ?: null,
            'barcode' => $this->first($data, ['barcode', 'ean', 'gtin', 'upc', 'codigo de barras', 'codigo barras']) ?: null,
            'brand' => $this->first($data, ['marca', 'brand']) ?: null,
            'category' => $this->first($data, ['categoria', 'categoria interna', 'category']) ?: null,
            'description' => $this->first($data, ['descripcion', 'description']) ?: null,
            'notes' => $this->first($data, ['notas', 'observaciones', 'notes']) ?: null,
            'price' => $this->number($priceValue),
            'cost_price' => $this->number($this->first($data, ['costo', 'precio costo', 'cost'])),
            'stock' => $stockValue === '' ? null : (int) $this->number($stockValue),
            'attributes' => $this->attributes($this->first($data, ['atributos', 'caracteristicas', 'attributes'])),
        ];
        $rowData['errors'] = [];
        if ($name === '') {
            $rowData['errors'][] = 'Falta el nombre.';
        }
        if ($rowData['price'] === null || $rowData['price'] < 0) {
            $rowData['errors'][] = 'El precio debe ser numérico y no negativo.';
        }
        if ($rowData['stock'] !== null && $rowData['stock'] < 0) {
            $rowData['errors'][] = 'El stock no puede ser negativo.';
        }
        $rowData['valid'] = $rowData['errors'] === [];

        return $rowData;
    }

    private function first(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (($data[$key] ?? '') !== '') {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    private function number(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $value = str_replace(['RD$', '$', ' ', ','], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function attributes(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return collect($decoded)->map(fn ($item, $name) => ['name' => (string) $name, 'value' => (string) $item])->values()->all();
        }

        return collect(preg_split('/[;|\n]+/', $value) ?: [])
            ->map(function (string $item) {
                [$name, $value] = array_pad(explode('=', $item, 2), 2, '');

                return ['name' => trim($name), 'value' => trim($value)];
            })
            ->filter(fn (array $item) => $item['name'] !== '' && $item['value'] !== '')
            ->values()
            ->all();
    }
}
