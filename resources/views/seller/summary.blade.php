<x-layouts.app :title="'Resumen | '.$shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => $canManage ? 'Mis tiendas' : 'Mi resumen', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.summary', $shop)],
        ['label' => 'Resumen'],
    ]" />
    @php($money = fn (int $cents) => 'RD$ '.number_format($cents / 100, 2))
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="summary" />
            <section class="overflow-hidden rounded-2xl bg-slate-900 p-6 text-white sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-blue-300">{{ $shop->name }} · {{ $canManage ? 'Operación' : 'Panel del vendedor' }}</p>
                        <h1 class="mt-3 text-2xl font-black sm:text-3xl">{{ $canManage ? 'Resumen de tu tienda' : 'Tu espacio de ventas' }}</h1>
                        <p class="mt-2 text-sm text-slate-300">Hola, {{ auth()->user()->name }}. {{ $canManage ? 'Así va la actividad de tu equipo.' : 'Aquí puedes seguir tus ventas y tus ganancias por comisión.' }}</p>
                    </div>
                    @if (in_array('sales', $visibleMenus, true))
                        <a wire:navigate href="{{ route('seller.shops.pos', $shop) }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold shadow-lg transition hover:bg-blue-500">＋ Nueva venta</a>
                    @endif
                </div>
            </section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-bold text-slate-900">{{ $canManage ? 'Actividad de tu tienda' : 'Tu resumen de ventas' }}</h2><p class="text-xs text-slate-500">{{ $periodLabel }} · Solo ventas válidas del periodo</p></div>
                <nav class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1" aria-label="Periodo del resumen">
                    @foreach (['today' => 'Hoy', 'week' => 'Últimos 7 días', 'month' => 'Este mes'] as $key => $label)
                        <a wire:navigate href="{{ route('seller.shops.summary', [$shop, 'period' => $key]) }}" @if ($period === $key) aria-current="true" @endif class="rounded-lg px-3 py-2 text-xs font-bold transition {{ $period === $key ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            @if (in_array('sales', $visibleMenus, true))
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => $canManage ? 'Ventas del periodo' : 'Tus ventas', 'value' => $money($metrics['total']), 'hint' => 'Importe vendido'],
                        ['label' => $canManage ? 'Comisiones del equipo' : 'Tus ganancias', 'value' => $money($metrics['commission']), 'hint' => 'Comisión registrada por venta'],
                        ['label' => 'Ventas realizadas', 'value' => number_format($metrics['count']), 'hint' => 'Operaciones del periodo'],
                        ['label' => 'Ticket promedio', 'value' => $money($metrics['average']), 'hint' => 'Importe promedio por venta'],
                    ] as $metric)
                        <section class="rounded-2xl border {{ $loop->index === 1 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
                            <h3 class="text-xs font-bold uppercase tracking-wide {{ $loop->index === 1 ? 'text-emerald-700' : 'text-slate-500' }}">{{ $metric['label'] }}</h3>
                            <p class="mt-3 text-2xl font-black {{ $loop->index === 1 ? 'text-emerald-800' : 'text-slate-900' }}">{{ $metric['value'] }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ $metric['hint'] }}</p>
                        </section>
                    @endforeach
                </div>
                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                        <h2 class="font-bold text-slate-900">{{ $canManage ? 'Ventas de la tienda' : 'Evolución de tus ventas' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Últimos 7 días · {{ $canManage ? 'Actividad del equipo' : 'Solo tus ventas' }}</p>
                        @php($maximum = max(1, $chart->max('total')))
                        <div class="mt-6 space-y-3" role="img" aria-label="Ventas diarias de los últimos 7 días">
                            @foreach ($chart as $day)
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="w-10 shrink-0 font-semibold text-slate-500">{{ $day['label'] }}</span>
                                    <div class="h-5 flex-1 overflow-hidden rounded-md bg-slate-100"><div class="h-full rounded-md bg-blue-600" style="width: {{ round($day['total'] * 100 / $maximum, 2) }}%"></div></div>
                                    <span class="w-28 shrink-0 text-right font-bold text-slate-700">{{ $money($day['total']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </section>
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="font-bold text-slate-900">{{ $canManage ? 'Comisiones del equipo' : 'Cómo se calculan tus ganancias' }}</h2>
                        <div class="mt-4 rounded-xl bg-emerald-50 p-4"><p class="text-xs font-semibold text-emerald-700">{{ $periodLabel }}</p><p class="mt-1 text-2xl font-black text-emerald-800">{{ $money($metrics['commission']) }}</p></div>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600">Las ganancias corresponden a la comisión guardada al registrar cada venta. Un cambio posterior en la comisión no modifica las ventas anteriores.</p>
                        <p class="mt-3 text-xs text-slate-500">Comisión generada; no indica que ya haya sido pagada.</p>
                    </section>
                </div>
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">{{ $canManage ? 'Ventas recientes del equipo' : 'Tus ventas recientes' }}</h2><p class="mt-1 text-xs text-slate-500">Detalle del periodo seleccionado</p></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Venta / fecha</th><th class="px-5 py-3">Cliente</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3 text-right">{{ $canManage ? 'Comisión' : 'Tu ganancia' }}</th><th class="px-5 py-3">Estado</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($sales as $sale)
                                    <tr><td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $sale->invoice_number }}</p><p class="mt-1 text-xs text-slate-500">{{ $sale->issued_at->format('d/m/Y h:i A') }}</p></td><td class="px-5 py-4 text-slate-600">{{ $sale->customer?->name ?? 'Consumidor final' }}</td><td class="whitespace-nowrap px-5 py-4 text-right font-bold">{{ $money(\App\Support\Money::toCents($sale->total)) }}</td><td class="whitespace-nowrap px-5 py-4 text-right font-bold text-emerald-700">{{ $money(\App\Support\Money::toCents($sale->commission_amount)) }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ ['paid' => 'Pagada', 'partial' => 'Pago parcial', 'pending' => 'Pendiente', 'unpaid' => 'Pendiente', 'draft' => 'Borrador'][$sale->status] ?? $sale->status }}</span></td></tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-12 text-center"><p class="font-semibold text-slate-700">Aún no hay ventas en este periodo</p><p class="mt-2 text-sm text-slate-500">Cuando registres una venta, aparecerán aquí su importe y comisión.</p></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($sales->hasPages())<div class="border-t border-slate-100 p-5">{{ $sales->links() }}</div>@endif
                </section>
            @endif
            <div class="flex flex-wrap gap-3">
                @if (in_array('products', $visibleMenus, true))<a wire:navigate href="{{ route('seller.shops.products.index', $shop) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Consultar productos →</a>@endif
                @if (in_array('customers', $visibleMenus, true))<a wire:navigate href="{{ route('seller.shops.customers.index', $shop) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Clientes →</a>@endif
            </div>
        </div>
    </main>
</x-layouts.app>
