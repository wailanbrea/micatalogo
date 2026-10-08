<x-layouts.app :title="'Cotización '.$quote->quote_number.' | '.$shop->name">
    <x-admin.header />

    <main class="min-h-screen bg-[#f7f7f6] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-[1500px] space-y-5">
            <x-seller.shop-header :shop="$shop" activeTab="quotes" />

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                <div class="flex flex-col gap-5 border-b border-slate-100 pb-6 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">Cotización</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ $quote->quote_number }}</h1>
                        <a wire:navigate.hover href="{{ route('seller.shops.feature', [$shop, 'feature' => 'quotes']) }}" class="mt-3 inline-flex text-sm font-bold text-slate-500 hover:text-blue-700">← Volver a cotizaciones</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('seller.shops.quotes.pdf', [$shop, 'quote' => $quote->public_id]) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">Descargar PDF</a>
                        @if ($quote->converted_invoice_id)
                            <span class="inline-flex items-center rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-black text-emerald-700">Convertida a venta</span>
                        @elseif ($quote->status === 'cancelled')
                            <span class="inline-flex items-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-black text-slate-600">Cancelada</span>
                        @elseif ($quote->isExpired())
                            <span class="inline-flex items-center rounded-xl bg-rose-50 px-4 py-2.5 text-sm font-black text-rose-700">Vencida</span>
                        @else
                            <form method="POST" action="{{ route('seller.shops.quotes.convert', [$shop, 'quote' => $quote->public_id]) }}">
                                @csrf
                                <button class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-black text-white hover:bg-emerald-700">Convertir en venta</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Cliente</p><p class="mt-2 font-bold text-slate-900">{{ $quote->customer_name ?: 'Cliente general' }}</p></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Estado</p><p class="mt-2 font-bold text-slate-900">{{ $quote->status === 'converted' ? 'Convertida' : ($quote->status === 'cancelled' ? 'Cancelada' : ($quote->isExpired() ? 'Vencida' : 'Vigente')) }}</p></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Válida hasta</p><p class="mt-2 font-bold text-slate-900">{{ $quote->valid_until?->format('d/m/Y') ?: 'Sin vencimiento' }}</p></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Artículos</p><p class="mt-2 font-bold text-slate-900">{{ number_format($quote->items->sum('quantity')) }}</p></div>
                </div>

                <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.14em] text-slate-500"><tr><th class="px-4 py-3">Producto</th><th class="px-4 py-3 text-right">Cant.</th><th class="px-4 py-3 text-right">Precio unit.</th><th class="px-4 py-3 text-right">Total línea</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($quote->items as $item)
                                <tr><td class="px-4 py-4 font-bold text-slate-900">{{ $item->product_name }}@if($item->volume_ml) <span class="font-normal text-slate-500">· {{ $item->volume_ml }} ml</span>@endif</td><td class="px-4 py-4 text-right text-slate-700">{{ $item->quantity }}</td><td class="px-4 py-4 text-right text-slate-700">RD$ {{ number_format((float) $item->unit_price, 2) }}</td><td class="px-4 py-4 text-right font-black text-slate-900">RD$ {{ number_format((float) $item->line_total, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="3" class="px-4 py-4 text-right font-black text-slate-700">Total</td><td class="px-4 py-4 text-right text-lg font-black text-slate-950">RD$ {{ number_format((float) $quote->total, 2) }}</td></tr></tfoot>
                    </table>
                </div>
                @if ($quote->notes)
                    <div class="mt-5 rounded-2xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-sm leading-6 text-blue-900"><strong>Notas:</strong> {{ $quote->notes }}</div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
