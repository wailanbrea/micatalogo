<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Anonymous deterministic workbook shared by PHP and Android end-to-end tests. */
class InventoryWorkbookFixture
{
    public static function write(string $path, int $products = 2, string $format = 'xlsx'): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Instrucciones')->setCellValue('A1', 'Exportación de inventario — datos anónimos');
        $sheet = $book->createSheet()->setTitle('Existencias');
        $sheet->fromArray(['Creado', 'Código del producto', 'Nombre', 'Categoría', 'Notas', 'Cant.', 'Costo unitario', 'Precio unitario', 'Costo inventario', 'Precio inventario', 'SKU', 'Color'], null, 'A8');
        for ($i = 0; $i < $products; $i++) {
            $r = $i + 9;
            $barcode = $i === 0 ? '0850050062035' : ($i === 1 ? '812256024194' : str_pad((string) (950050062000 + $i), 13, '0', STR_PAD_LEFT));
            $sheet->fromArray(['2026-10-01', $barcode, 'Producto anónimo '.($i + 1), 'Perfumes', 'Muestra sin datos de clientes', '10,0', '1500,25', '2500,50', '15002,50', '25005,00', str_pad((string) (45 + $i), 6, '0', STR_PAD_LEFT), 'Azul'], null, 'A'.$r);
            $sheet->setCellValueExplicit('B'.$r, $barcode, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('K'.$r, str_pad((string) (45 + $i), 6, '0', STR_PAD_LEFT), DataType::TYPE_STRING);
        }
        $writer = $format === 'xls' ? new Xls($book) : new Xlsx($book);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $book->disconnectWorksheets();
    }
}
