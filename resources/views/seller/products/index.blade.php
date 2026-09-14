<x-layouts.app :title="'Productos | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Productos']
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <!-- Shop Context & Local Navigation Tabs -->
            <x-seller.shop-header :shop="$shop" activeTab="products" />

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                @if (session('status'))
                    <div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">Productos</h1>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $totalProducts }} de {{ $maxProducts }} productos en tu plan gratuito
                        </p>
                    </div>

                    @if ($totalProducts < $maxProducts)
                        <div class="flex items-center gap-2">
                            <a class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50" href="{{ route('seller.shops.products.bulk.create', $shop) }}">
                                Subida masiva
                            </a>
                            <a class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-blue-700" href="{{ route('seller.shops.products.create', $shop) }}">
                                + Nuevo producto
                            </a>
                        </div>
                    @else
                        <span class="rounded-md bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">Límite alcanzado ({{ $maxProducts }}/{{ $maxProducts }})</span>
                    @endif
                </div>

                <!-- Filtros -->
                <form class="mt-5 flex flex-wrap items-center gap-3" method="GET" action="{{ route('seller.shops.products.index', $shop) }}">
                    <div class="min-w-64 flex-1">
                        <label class="sr-only" for="q">Buscar producto</label>
                        <input class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="q" name="q" placeholder="Buscar por nombre..." type="search" value="{{ $search }}">
                    </div>
                    <div>
                        <label class="sr-only" for="status">Disponibilidad</label>
                        <select class="rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm text-slate-700 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="status" name="status" onchange="this.form.submit()">
                            <option value="">Todas las disponibilidades</option>
                            <option value="available" @selected($status === 'available')>Disponible</option>
                            <option value="out_of_stock" @selected($status === 'out_of_stock')>Agotado</option>
                        </select>
                    </div>
                    @if ($search || $status)
                        <a class="text-xs font-semibold text-slate-500 hover:text-slate-800" href="{{ route('seller.shops.products.index', $shop) }}">Limpiar filtros</a>
                    @endif
                </form>

                <!-- Listado -->
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase font-semibold text-slate-700">
                            <tr>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3">Precio</th>
                                <th class="px-4 py-3">Categoría</th>
                                <th class="px-4 py-3">Disponibilidad</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($products as $product)
                                <tr class="hover:bg-slate-50/75 transition">
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded border border-slate-200 bg-slate-100">
                                                @if ($product->images->isNotEmpty())
                                                    <img class="h-full w-full object-cover" src="{{ $product->images->first()->thumbnail_url }}" alt="{{ $product->name }}">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-slate-400">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Zm5-12h.01"/></svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-slate-900 truncate">{{ $product->name }}</p>
                                                <p class="text-xs text-slate-400">{{ $product->slug }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-medium text-slate-900 whitespace-nowrap">
                                        RD$ {{ number_format((float) $product->price, 0) }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        {{ $product->globalCategory?->name ?? 'General' }}
                                        @if ($product->shopCategory)
                                            <span class="block text-xs text-slate-400">{{ $product->shopCategory->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if ($product->availability_status->value === 'available')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i> Disponible
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-amber-500"></i> Agotado
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 capitalize">
                                            {{ $product->moderation_status->value }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <a class="text-xs font-semibold text-blue-700 hover:text-blue-900" href="{{ route('products.show', [$shop, $product]) }}" target="_blank">Ver</a>
                                            <a class="text-xs font-semibold text-slate-700 hover:text-slate-950" href="{{ route('seller.shops.products.edit', [$shop, $product]) }}">Editar</a>
                                            <form method="POST" action="{{ route('seller.shops.products.destroy', [$shop, $product]) }}" onsubmit="return confirm('¿Deseas enviar este producto a la papelera?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs font-semibold text-red-600 hover:text-red-800" type="submit">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-8 text-center text-slate-500" colspan="6">
                                        No se encontraron productos en esta tienda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($products->hasPages())
                    <div class="mt-6 border-t border-slate-200 pt-4">
                        {{ $products->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
