<?php

namespace App\Services\InventoryImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use ZipArchive;

class WorkbookReader
{
    public const MAX_ROWS = 5050;

    public const MAX_COLUMNS = 100;

    public const MAX_SHEETS = 20;

    public function read(UploadedFile $file): array
    {
        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->fail('El archivo supera 10 MB.');
        }
        $type = strtolower($file->getClientOriginalExtension());
        if ($type === 'pdf') {
            return app(PdfCatalogReader::class)->read($file);
        }
        if (in_array($type, ['csv', 'txt'], true)) {
            return $this->csv($file->getRealPath(), $type);
        }
        if (! in_array($type, ['xlsx', 'xls'], true)) {
            $this->fail('Usa XLSX, XLS, CSV, TXT o PDF. El PDF debe tener texto seleccionable.');
        }
        if ($type === 'xlsx') {
            $this->checkArchive($file->getRealPath());
        }
        $reader = IOFactory::createReader($type === 'xls' ? 'Xls' : 'Xlsx');
        if (! $reader->canRead($file->getRealPath())) {
            $this->fail('El contenido no coincide con el formato Excel indicado.');
        }
        $info = $reader->listWorksheetInfo($file->getRealPath());
        if (count($info) > self::MAX_SHEETS) {
            $this->fail('El libro supera 20 hojas.');
        }
        if (array_sum(array_map(fn ($sheet) => $sheet['totalRows'] * $sheet['totalColumns'], $info)) > 100000) {
            $this->fail('El libro supera 100000 celdas; divide el inventario en archivos más pequeños.');
        }
        foreach ($info as $sheet) {
            if ($sheet['totalRows'] > self::MAX_ROWS || $sheet['totalColumns'] > self::MAX_COLUMNS) {
                $this->fail('El libro supera 5050 filas o 100 columnas por hoja.');
            }
        }
        $reader->setReadDataOnly(false); // Formats are required to recover leading zero identifiers.
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= WorkbookReader::MAX_ROWS && Coordinate::columnIndexFromString($columnAddress) <= WorkbookReader::MAX_COLUMNS;
            }
        });
        $book = $reader->load($file->getRealPath());
        $sheets = $warnings = [];
        foreach ($book->getWorksheetIterator() as $index => $sheet) {
            $rows = [];
            $numericColumns = [];
            $height = min(self::MAX_ROWS, $sheet->getHighestDataRow());
            $width = min(self::MAX_COLUMNS, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
            for ($r = 1; $r <= $height; $r++) {
                $values = [];
                for ($c = 1; $c <= $width; $c++) {
                    $cell = $sheet->getCell([$c, $r]);
                    $value = $cell->getValue();
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        // Never evaluate uploaded formulas or external links.
                        $value = $cell->getOldCalculatedValue();
                        $warnings['formula'] = 'Se utilizan resultados guardados de fórmulas; las fórmulas no se ejecutan. Revisa sus valores.';
                    } elseif ($cell->getDataType() === DataType::TYPE_NUMERIC) {
                        $mask = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        if (preg_match('/^0{2,}$/D', $mask) && preg_match('/^\d+$/D', (string) $value)) {
                            $value = str_pad((string) $value, strlen($mask), '0', STR_PAD_LEFT);
                        } elseif (is_numeric($value) && (str_contains(strtolower((string) $value), 'e') || abs((float) $value) >= 1e15)) {
                            $warnings['precision'] = 'Hay números con notación científica o más de 15 dígitos: Excel puede haber perdido precisión. Usa texto para los códigos.';
                            $value = rtrim(rtrim(sprintf('%.12F', (float) $value), '0'), '.');
                        }
                        if (! preg_match('/^0{2,}$/D', $mask)) {
                            $numericColumns[$c - 1] = true;
                        }
                    }
                    if ($value instanceof RichText) {
                        $value = $value->getPlainText();
                    }
                    if (strlen((string) $value) > 16000) {
                        $this->fail('Una celda supera 16000 caracteres.');
                    }
                    $values[] = trim((string) $value);
                }
                $rows[] = $values;
            }
            $sheets[] = [
                'name' => $sheet->getTitle(),
                'index' => $index,
                'rows' => $rows,
                'image_rows' => $this->worksheetImages($sheet),
                'unformatted_numeric_columns' => array_keys($numericColumns),
            ];
        }
        $book->disconnectWorksheets();

        return ['type' => $type, 'sheets' => $sheets, 'warnings' => array_values($warnings)];
    }

    private function checkArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('El XLSX no es un archivo ZIP válido.');
        }
        try {
            $total = 0;
            if ($zip->numFiles > 2000) {
                $this->fail('El XLSX contiene demasiadas entradas.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                if ($total > 64 * 1024 * 1024 || $entry['size'] > 24 * 1024 * 1024 || $entry['size'] / max(1, $entry['comp_size']) > 1000) {
                    $this->fail('El XLSX excede los límites de descompresión segura.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function csv(string $path, string $type): array
    {
        $content = file_get_contents($path);
        $warnings = [];
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            $warnings[] = 'Archivo convertido de Windows-1252/ISO-8859-1 a UTF-8. Revisa los caracteres de la muestra.';
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $best = [];
        $bestScore = -1;
        $selectedDelimiter = ',';
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $content);
            rewind($handle);
            $rows = [];
            while (count($rows) < 100 && ($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $rows[] = array_map(fn ($v) => trim((string) $v), $row);
            }
            fclose($handle);
            $counts = array_count_values(array_map('count', array_filter(array_slice($rows, 0, 100), fn ($row) => count($row) > 1)));
            $score = $counts ? max($counts) * 10 + max(array_keys($counts)) : 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $selectedDelimiter = $delimiter;
            }
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $selectedDelimiter, '"', '')) !== false) {
            if (count($best) >= self::MAX_ROWS || count($row) > self::MAX_COLUMNS) {
                fclose($handle);
                $this->fail('El archivo supera 5050 filas o 100 columnas.');
            }
            foreach ($row as $value) {
                if (strlen((string) $value) > 16000) {
                    fclose($handle);
                    $this->fail('Una celda supera 16000 caracteres.');
                }
            }
            $best[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return ['type' => $type, 'sheets' => [['name' => 'Inventario', 'index' => 0, 'rows' => $best]], 'warnings' => $warnings];
    }

    /**
     * Keep small embedded spreadsheet images associated with their row. URLs
     * in an Imagen column remain the preferred option for large workbooks.
     *
     * @return array<int, array<int, array{bytes: string, mime_type: string}>>
     */
    private function worksheetImages($sheet): array
    {
        $imageRows = [];
        $totalBytes = 0;
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $bytes = null;
            $mime = null;
            if (method_exists($drawing, 'getPath')) {
                $drawingPath = (string) $drawing->getPath();
                // PhpSpreadsheet exposes embedded XLSX media through a
                // zip:// stream after the workbook is loaded.
                $bytes = @file_get_contents($drawingPath) ?: null;
                $mime = method_exists($drawing, 'getMimeType') ? $drawing->getMimeType() : null;
            } elseif (method_exists($drawing, 'getImageResource')) {
                $resource = $drawing->getImageResource();
                if ($resource) {
                    ob_start();
                    imagepng($resource);
                    $bytes = ob_get_clean() ?: null;
                    $mime = 'image/png';
                }
            }
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 2_000_000 || $totalBytes + strlen($bytes) > 5_000_000) {
                continue;
            }
            $info = @getimagesizefromstring($bytes);
            $detectedMime = (string) ($info['mime'] ?? $mime ?? '');
            if (! in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
                continue;
            }
            $row = (int) preg_replace('/\D+/', '', (string) $drawing->getCoordinates());
            if ($row < 1) {
                continue;
            }
            $totalBytes += strlen($bytes);
            $imageRows[$row][] = ['bytes' => $bytes, 'mime_type' => $detectedMime];
        }

        return $imageRows;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
