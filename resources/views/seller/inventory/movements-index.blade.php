<x-layouts.app :title="'Movimientos de inventario | ' . $shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
        ['label' => 'Movimientos de inventario']
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="inventory" />
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Catálogo · Inventario</p>
                        <h1 class="mt-1 text-2xl font-black text-slate-950">Movimientos</h1>
                        <p class="mt-1 text-sm text-slate-500">Entradas, ventas y ajustes de todos los productos, con su historial intacto.</p>
                    </div>
                    <a href="{{ route('seller.shops.inventory.index', $shop) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:border-blue-200 hover:text-blue-700">Volver al inventario</a>
                </div>

                <form method="GET" action="{{ route('seller.shops.inventory.movements.index', $shop) }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_220px_auto]">
                    <label class="sr-only" for="movement-q">Buscar producto</label>
                    <input id="movement-q" name="q" value="{{ $search }}" placeholder="Buscar producto..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <label class="sr-only" for="movement-type">Tipo de movimiento</label>
                    <select id="movement-type" name="type" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="">Todos los movimientos</option>
                        <option value="restock" @selected($type === 'restock')>Reposiciones</option>
                        <option value="sale" @selected($type === 'sale')>Ventas</option>
                        <option value="adjustment" @selected($type === 'adjustment')>Ajustes</option>
                    </select>
                    <button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white hover:bg-slate-800">Filtrar</button>
                </form>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wide text-slate-600">
                            <tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Producto</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3 text-right">Variación</th><th class="px-4 py-3 text-right">Stock después</th><th class="px-4 py-3">Usuario / nota</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($movements as $movement)
                                @php($positive = $movement->quantity > 0)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $movement->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-900">{{ $movement->product?->name ?? 'Producto eliminado' }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-black {{ $movement->type === 'sale' ? 'bg-emerald-50 text-emerald-700' : ($movement->type === 'restock' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700') }}">{{ $movement->typeLabel() }}</span></td>
                                    <td class="px-4 py-3 text-right font-black {{ $positive ? 'text-blue-700' : 'text-emerald-700' }}">{{ $positive ? '+' : '' }}{{ number_format($movement->quantity) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900">{{ number_format($movement->stock_after) }}</td>
                                    <td class="max-w-xs px-4 py-3 text-xs text-slate-500"><span class="block font-semibold text-slate-700">{{ $movement->user?->name ?? 'Sistema' }}</span>{{ $movement->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">No hay movimientos que coincidan con los filtros.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($movements->hasPages())<div class="mt-5 border-t border-slate-100 pt-4">{{ $movements->links() }}</div>@endif
            </section>
        </div>
    </main>
</x-layouts.app>
