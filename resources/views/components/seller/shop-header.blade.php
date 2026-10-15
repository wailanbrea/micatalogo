@props(['shop', 'activeTab' => 'products', 'productCount' => null, 'product' => null])
@php
    $product = $product ?? request()->route('product');
@endphp

<div class="relative border-b border-slate-200 bg-white md:hidden" x-data="{}">
    <button type="button" @click="$dispatch('open-store-menu')" aria-controls="mobile-panel-navigation" class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-bold text-slate-700">
        <span>{{ $shop->name }}</span><span>Abrir menú</span>
    </button>
    @if ($activeTab === 'inventory' && $product)
        @php
            $salesHistory = $product->inventoryMovements()->where('type', 'sale')->whereNotNull('unit_price')->get()
                ->groupBy(fn ($movement) => $movement->created_at->format('Y-m-d'))
                ->map(fn ($day) => $day->sum(fn ($movement) => abs((int) $movement->quantity) * (float) $movement->unit_price))->sortKeys();
            $salesHistoryData = [
                'labels' => $salesHistory->keys()->map(fn ($date) => \Carbon\Carbon::parse($date)->format('d/m'))->values()->all(),
                'revenue' => $salesHistory->values()->map(fn ($value) => round((float) $value, 2))->values()->all(),
            ];
        @endphp
        <div class="border-t border-slate-100 bg-slate-50/70 px-4 py-4 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><p class="text-xs font-bold text-slate-900">Ingresos de {{ $product->name }}</p><p class="text-[11px] text-slate-500">Historial de ventas por día</p></div>
                <span class="text-xs font-black text-emerald-700">RD$ {{ number_format((float) $salesHistory->sum(), 0) }}</span>
            </div>
            @if ($salesHistory->isNotEmpty())
                <div class="mt-3 h-44"><canvas data-sales-chart data-chart-metric="revenue" data-chart='@json($salesHistoryData)' aria-label="Ingresos históricos del producto"></canvas></div>
            @else
                <p class="mt-3 text-xs text-slate-400">Este producto todavía no tiene ventas registradas.</p>
            @endif
        </div>
    @endif
</div>
