<x-layouts.app :title="'Historial de Inventario | ' . $product->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Inventario', 'url' => route('seller.shops.inventory.index', $shop)],
            ['label' => $product->name]
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <!-- Shop Context Header -->
            <x-seller.shop-header :shop="$shop" activeTab="inventory" />

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('seller.shops.inventory.index', $shop) }}" class="rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            </a>
                            <h1 class="text-xl font-bold text-slate-900">Historial de Movimientos</h1>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Producto: <span class="font-bold text-slate-800">{{ $product->name }}</span>
                            @if ($product->inventory)
                                · Stock actual: <span class="font-bold text-slate-900">{{ $product->inventory->stock_quantity }}</span>
                                · Total vendidos: <span class="font-bold text-emerald-700">{{ $product->inventory->sold_quantity }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('seller.shops.inventory.index', $shop) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Volver al inventario
                        </a>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase font-semibold text-slate-700">
                            <tr>
                                <th class="px-4 py-3">Fecha y Hora</th>
                                <th class="px-4 py-3">Tipo de Operación</th>
                                <th class="px-4 py-3 text-right">Variación</th>
                                <th class="px-4 py-3 text-right">Stock Antes</th>
                                <th class="px-4 py-3 text-right">Stock Después</th>
                                <th class="px-4 py-3">Nota / Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse ($movements as $movement)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="px-4 py-3 font-mono text-slate-500 whitespace-nowrap">
                                        {{ $movement->created_at->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($movement->type === 'sale')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i> Venta
                                            </span>
                                        @elseif ($movement->type === 'restock')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-blue-500"></i> Reposición
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-amber-500"></i> Ajuste
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold whitespace-nowrap {{ $movement->quantity > 0 ? 'text-blue-600' : ($movement->quantity < 0 ? 'text-emerald-600' : 'text-slate-500') }}">
                                        {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-500 whitespace-nowrap">
                                        {{ $movement->stock_before }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-black text-slate-900 whitespace-nowrap">
                                        {{ $movement->stock_after }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 italic">
                                        {{ $movement->notes ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-8 text-center text-slate-400" colspan="6">
                                        No hay movimientos registrados para este producto todavía.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($movements->hasPages())
                    <div class="mt-6 border-t border-slate-200 pt-4">
                        {{ $movements->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
