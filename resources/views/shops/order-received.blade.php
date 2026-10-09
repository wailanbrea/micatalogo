@php
    $statusLabel = $order->invoice_id ? 'Confirmado' : 'Pendiente de confirmación';
@endphp

<x-layouts.app
    :title="'Pedido '.$order->order_number.' | '.$shop->name"
    :description="'Resumen del pedido enviado a '.$shop->name"
>
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                <div class="bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 px-6 py-8 text-white sm:px-9">
                    <div class="flex items-center gap-3">
                        @if ($shop->logo_url)
                            <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-12 w-12 rounded-2xl border border-white/30 object-cover">
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-xl font-black">{{ str($shop->name)->substr(0, 1)->upper() }}</div>
                        @endif
                        <div>
                            <p class="text-sm font-bold text-blue-100">{{ $shop->name }}</p>
                            <h1 class="mt-1 text-2xl font-black tracking-tight">¡Pedido recibido!</h1>
                        </div>
                    </div>
                    <p class="mt-6 max-w-lg text-sm leading-6 text-blue-50">El vendedor recibió tu pedido y te escribirá por WhatsApp para confirmar disponibilidad, entrega y pago.</p>
                </div>

                <div class="space-y-6 p-5 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Pedido</p>
                            <p class="mt-1 text-xl font-black text-slate-950">{{ $order->order_number }}</p>
                        </div>
                        <span class="rounded-full {{ $order->invoice_id ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }} px-3 py-1.5 text-xs font-black">{{ $statusLabel }}</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">Cliente</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $order->customer_name ?: 'Cliente general' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">WhatsApp</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $order->customer_phone ?: 'No indicado' }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h2 class="text-base font-black text-slate-950">Detalle del pedido</h2>
                            <span class="text-xs font-bold text-slate-500">{{ $order->items->sum('quantity') }} artículo(s)</span>
                        </div>
                        <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200">
                            @foreach ($order->items as $item)
                                <div class="flex items-center justify-between gap-4 px-4 py-3.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $item->product_name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $item->quantity }} × RD$ {{ number_format((float) $item->unit_price, 2) }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-black tabular-nums text-slate-900">RD$ {{ number_format((float) $item->line_total, 2) }}</p>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between bg-slate-50 px-4 py-4">
                                <span class="text-sm font-black text-slate-700">Total</span>
                                <span class="text-xl font-black tabular-nums text-slate-950">RD$ {{ number_format((float) $order->total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3.5 text-sm font-black text-white shadow-lg shadow-emerald-100 transition hover:bg-emerald-700">Continuar por WhatsApp</a>
                    <a href="{{ route('shops.show', $shop) }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 px-4 py-3.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">Volver a la tienda</a>
                </div>
            </section>
        </div>
    </main>
</x-layouts.app>
