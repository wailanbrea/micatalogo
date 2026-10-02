<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 0; }
        .header { border-bottom: 2px solid #2563eb; margin-bottom: 24px; padding-bottom: 14px; }
        .brand { color: #1d4ed8; font-size: 21px; font-weight: bold; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eff6ff; color: #1e3a8a; font-size: 10px; padding: 9px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e2e8f0; padding: 10px 9px; }
        .total { font-size: 18px; font-weight: bold; margin-top: 20px; text-align: right; }
        .status { color: #047857; font-weight: bold; }
        .footer { border-top: 1px solid #e2e8f0; color: #64748b; margin-top: 42px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="border: 0; padding: 0;">
                    <div class="brand">MiCatalogo</div>
                    <div class="muted">Factura de {{ $invoice->shop->name }}</div>
                </td>
                <td class="right" style="border: 0; padding: 0;">
                    <strong>{{ $invoice->invoice_number }}</strong><br>
                    <span class="muted">{{ $invoice->issued_at->format('d/m/Y H:i') }}</span><br>
                    <span class="status">PAGADA</span>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Presentación</th>
                <th class="right">Cantidad</th>
                <th class="right">Precio</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->sale_unit === 'decant' ? $item->volume_ml.' ml' : $item->sale_unit }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">RD$ {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">RD$ {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Total: RD$ {{ number_format((float) $invoice->total, 2) }}</div>
    <div class="footer">Gracias por su compra. Documento generado por MiCatalogo.</div>
</body>
</html>
