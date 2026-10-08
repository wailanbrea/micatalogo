<x-layouts.app :title="'Lotes de Inventario (FIFO) | ' . $shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Inventario', 'url' => route('seller.shops.inventory.index', $shop)],
            ['label' => 'Lotes FIFO']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <x-seller.shop-header :shop="$shop" activeTab="inventory" />

            <!-- Title & Top Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-100 text-blue-800 font-black text-lg shadow-2xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </span>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Lotes de inventario (FIFO)</h1>
                            <p class="mt-0.5 text-xs text-slate-500">Historial y saldos de mercancía registrada por orden de entrada.@if ($showCosts) Costo unitario real incluido.@endif</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('seller.shops.inventory.index', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>← Volver a Inventario</span>
                    </a>
                    @if ($showCosts)
                        <a href="{{ route('seller.shops.business', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                            <span>Resumen financiero ↗</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Lots Table Section -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-base font-black text-slate-900">Historial de lotes registrados</h2>
                        <p class="text-xs text-slate-500">Las existencias se consumen en orden First-In, First-Out (primero en entrar, primero en salir) al facturar ventas.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 tabular-nums">
                        {{ $lots->total() }} {{ $lots->total() === 1 ? 'lote' : 'lotes' }}
                    </span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Fecha recepción</th>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3 text-right">Recibido</th>
                                <th class="px-4 py-3 text-right">Restante</th>
                                @if ($showCosts)
                                    <th class="px-4 py-3 text-right">Costo de entrada</th>
                                    <th class="px-4 py-3 text-right">Costo restante</th>
                                @endif
                                <th class="px-4 py-3">Origen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($lots as $lot)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 text-slate-600 font-mono">
                                        {{ $lot->received_at->format('d/m/Y h:i A') }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-900">
                                        {{ $products[$lot->product_id]->name ?? 'Producto #' . $lot->product_id }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-800 tabular-nums whitespace-nowrap">
                                        {{ number_format($lot->received_quantity) }} {{ $lot->quantity_unit }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-black tabular-nums whitespace-nowrap {{ $lot->remaining_quantity > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                        {{ number_format($lot->remaining_quantity) }} {{ $lot->quantity_unit }}
                                    </td>
                                    @if ($showCosts)
                                        <td class="px-4 py-3 text-right font-semibold text-slate-800 tabular-nums whitespace-nowrap">
                                            @if ($lot->received_product_unit_cost_cents !== null)
                                                RD$ {{ number_format($lot->received_product_unit_cost_cents / 100.0, 2) }}
                                                <span class="block text-[10px] font-normal text-slate-500">
                                                    por {{ $lot->product?->sale_unit === 'bottle' ? 'botella' : ($lot->product?->sale_unit === 'ml' ? 'ml' : 'unidad') }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                            {{ $lot->remaining_cost_cents !== null ? 'RD$ ' . number_format($lot->remaining_cost_cents / 100.0, 2) : '—' }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 capitalize">
                                            {{ $lot->origin }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $showCosts ? 7 : 5 }}" class="px-4 py-8 text-center text-slate-400">
                                        Sin lotes de inventario registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($lots->hasPages())
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        {{ $lots->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
