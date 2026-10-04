<x-layouts.app title="Control del negocio">
    <x-seller.shop-header :shop="$shop" active-tab="business" />
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8">
        <h1 class="text-2xl font-bold">{{ $shop->name }} · Control del negocio</h1>
        <a class="text-blue-700 underline" href="{{ route('seller.shops.inventory.index', $shop) }}">Ventas e inventario</a>
        @if(session('status'))<p class="rounded-xl bg-emerald-50 p-4">{{ session('status') }}</p>@endif
        @foreach($errors->all() as $error)<p class="text-red-700">{{ $error }}</p>@endforeach
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach(['Ventas hoy' => 'RD$ '.number_format($today, 2), 'Ventas del período' => 'RD$ '.number_format($total, 2), 'Cuentas por cobrar' => 'RD$ '.number_format($receivable, 2), 'Stock bajo' => $inventory['low_stock_count'], 'Agotados' => $inventory['out_of_stock_count']] as $label => $value)
                <div class="rounded-xl border bg-white p-4"><p class="text-sm text-slate-500">{{ $label }}</p><p class="text-xl font-bold">{{ $value }}</p></div>
            @endforeach
        </div>
        <form class="flex flex-wrap gap-3"><label>Desde <input class="rounded border p-2" type="date" name="from" value="{{ $from }}"></label><label>Hasta <input class="rounded border p-2" type="date" name="to" value="{{ $to }}"></label><button class="rounded bg-blue-700 px-4 text-white">Consultar</button></form>
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-lg font-bold">Ventas y ganancia por producto</h2>
            <p class="text-sm text-slate-500">Ganancia bruta antes de gastos y comisiones. Impuestos excluidos; descuentos generales prorrateados conservando los centavos. Las devoluciones se descuentan en la fecha del reembolso; solo la mercancía repuesta recupera su costo. Descuentos generales del período: RD$ {{ number_format($discount, 2) }}.</p>
            <div class="overflow-x-auto"><table class="mt-4 w-full text-left text-sm"><thead><tr><th>Producto</th><th>Vendidos</th><th>Ingreso neto</th><th>Costo FIFO</th><th>Ganancia bruta</th></tr></thead><tbody>
            @forelse($products as $row)<tr class="border-t"><td class="py-3">{{ $row->product_name }}</td><td>{{ $row->units }}</td><td>RD$ {{ number_format($row->revenue, 2) }}</td><td>{{ $row->unknown_lines ? 'Costo incompleto' : 'RD$ '.number_format($row->known_cost, 2) }}</td><td>{{ $row->unknown_lines ? 'No calculable: faltan costos' : 'RD$ '.number_format($row->revenue - $row->known_cost, 2) }}</td></tr>@empty<tr><td class="py-4" colspan="5">Sin ventas en este período.</td></tr>@endforelse
            </tbody></table></div>
        </section>
        @can('update', $shop)
        <section class="rounded-xl border bg-white p-5"><h2 class="text-lg font-bold">Lotes de inventario</h2><p class="text-sm text-slate-500">Los saldos iniciales usan el costo registrado al comenzar FIFO; no reconstruyen compras anteriores.</p><div class="overflow-x-auto"><table class="mt-4 w-full text-left text-sm"><thead><tr><th>Producto</th><th>Recibido</th><th>Restante</th><th>Costo recibido</th><th>Origen</th></tr></thead><tbody>@foreach($lots as $lot)<tr class="border-t"><td class="py-2">{{ $inventory['all_products']->firstWhere('id', $lot->product_id)?->name }}</td><td>{{ $lot->received_quantity }} {{ $lot->quantity_unit }}</td><td>{{ $lot->remaining_quantity }} {{ $lot->quantity_unit }}</td><td>{{ $lot->received_cost_cents === null ? 'Desconocido' : 'RD$ '.number_format($lot->received_cost_cents / 100, 2) }}</td><td>{{ $lot->origin }}</td></tr>@endforeach</tbody></table></div></section>
        <section class="rounded-xl border bg-white p-5"><h2 class="text-lg font-bold">Reglas de precio · Pro</h2><p class="text-sm text-slate-500">Margen sobre precio de venta y redondeo hacia arriba. Las bajadas siempre necesitan aprobación.</p>
            @foreach($inventory['all_products']->where('sale_unit', '!=', 'decant') as $product)
                @php($rule = $rules->get($product->id))
                <div class="mt-4 border-t pt-4"><h3 class="font-semibold">{{ $product->name }} · RD$ {{ number_format($product->price, 2) }}</h3>
                <form class="mt-2 flex flex-wrap items-center gap-3" method="POST" action="{{ route('seller.shops.pricing.rule', [$shop, $product]) }}">@csrf
                    <label>Margen % <input class="w-20 rounded border p-2" name="margin_percent" type="number" min="0" max="95" step="0.01" value="{{ $rule->margin_percent ?? 40 }}" required></label>
                    <label>Redondear a RD$ <input class="w-24 rounded border p-2" name="round_step" type="number" min="0.01" step="0.01" value="{{ ($rule->round_step_cents ?? 100) / 100 }}" required></label>
                    <label><input name="auto_increase" type="checkbox" value="1" @checked($rule?->auto_increase)> Aplicar subidas automáticamente</label><button class="rounded bg-blue-700 px-4 py-2 text-white">Guardar regla</button>
                </form>
                @if($rule?->pending_price !== null)<form class="mt-3" method="POST" action="{{ route('seller.shops.pricing.approve', [$shop, $product]) }}">@csrf<input name="expected_price" type="hidden" value="{{ $rule->pending_price }}"><button class="rounded bg-amber-100 p-2">Aprobar precio propuesto: RD$ {{ number_format($rule->pending_price, 2) }}</button></form>@endif
                </div>
            @endforeach
        </section>
        @endcan
        <section class="rounded-xl border bg-white p-5"><h2 class="text-lg font-bold">Pedidos pendientes de confirmar</h2><p class="text-sm text-slate-500">El pedido no reserva existencias. Confirma solo después de acordar y cobrar la venta.</p>
            @forelse($orders as $order)<div class="mt-3 border-t py-3"><p>{{ $order->order_number }} · {{ $order->customer_name }} · RD$ {{ number_format($order->total, 2) }}</p><p class="text-sm">{{ $order->items->map(fn($item) => $item->quantity.' × '.$item->product_name)->join(', ') }}</p><form method="POST" action="{{ route('seller.shops.orders.confirm', [$shop, $order]) }}">@csrf<button class="mt-2 rounded bg-emerald-700 px-4 py-2 text-white">Confirmar venta cobrada</button></form></div>@empty<p class="mt-4">Sin pedidos pendientes.</p>@endforelse
            {{ $orders->links() }}
        </section>
    </main>
</x-layouts.app>
