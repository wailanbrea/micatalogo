<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\ShopSalesReadService;
use Illuminate\Http\Request;

class SalesExportController extends Controller
{
    public function show(Request $request, Shop $shop, ShopSalesReadService $service)
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);
        $query = $service->query($shop, $request)->latest('issued_at');
        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Factura', 'Fecha', 'Cliente', 'Vendedor', 'Subtotal', 'Descuento', 'Impuesto', 'Total', 'Estado'], ',', '"', '');
            foreach ($query->cursor() as $invoice) {
                $fields = [$invoice->invoice_number, $invoice->issued_at?->toIso8601String(), $invoice->customer?->name ?? 'Venta general',
                    $invoice->user?->name, $invoice->subtotal, $invoice->discount, $invoice->tax, $invoice->total, $invoice->status];
                $fields = array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $fields);
                fputcsv($stream, $fields, ',', '"', '');
            }
            fclose($stream);
        }, 'ventas-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
