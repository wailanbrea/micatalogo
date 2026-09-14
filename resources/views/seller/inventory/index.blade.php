<x-layouts.app :title="'Control de Inventario | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Control de Inventario']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8" x-data="inventoryManager()">
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Shop Context & Local Navigation Tabs -->
            <x-seller.shop-header :shop="$shop" activeTab="inventory" />

            <!-- Flash Status Messages -->
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800 shadow-xs">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- KPI Metric Cards Grid -->
            <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <!-- Unidades Disponibles -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Disponibles</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($summary['total_available']) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Unidades en stock</p>
                </div>

                <!-- Unidades Vendidas -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Vendidas</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($summary['total_sold']) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Unidades despachadas</p>
                </div>

                <!-- Stock Bajo -->
                <div class="rounded-xl border {{ $summary['low_stock_count'] > 0 ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider {{ $summary['low_stock_count'] > 0 ? 'text-amber-800' : 'text-slate-500' }}">Stock bajo</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg {{ $summary['low_stock_count'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-black {{ $summary['low_stock_count'] > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $summary['low_stock_count'] }}</p>
                    <p class="mt-0.5 text-[11px] {{ $summary['low_stock_count'] > 0 ? 'text-amber-800 font-medium' : 'text-slate-500' }}">Productos por agotarse</p>
                </div>

                <!-- Agotados -->
                <div class="rounded-xl border {{ $summary['out_of_stock_count'] > 0 ? 'border-rose-300 bg-rose-50/50' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider {{ $summary['out_of_stock_count'] > 0 ? 'text-rose-800' : 'text-slate-500' }}">Agotados</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg {{ $summary['out_of_stock_count'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-black {{ $summary['out_of_stock_count'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $summary['out_of_stock_count'] }}</p>
                    <p class="mt-0.5 text-[11px] {{ $summary['out_of_stock_count'] > 0 ? 'text-rose-800 font-medium' : 'text-slate-500' }}">Sin existencias</p>
                </div>

                <!-- Valor de Inventario a Costo -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Valor Stock</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xl font-black text-slate-900 truncate">RD$ {{ number_format($summary['total_inventory_value'], 0) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Inversión mercancía</p>
                </div>

                <!-- Ganancia Bruta Estimada -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ganancia Est.</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xl font-black text-violet-700 truncate">RD$ {{ number_format($summary['total_gross_profit'], 0) }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Margen ventas</p>
                </div>
            </section>

            <!-- Alertas Críticas y Ranking (Top 3 Cards) -->
            <div class="grid gap-5 md:grid-cols-3">
                <!-- Productos Por Agotarse -->
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                Productos por agotarse
                            </h2>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                {{ $summary['low_stock_products']->count() }}
                            </span>
                        </div>
                        <ul class="mt-3 divide-y divide-slate-100 text-xs">
                            @forelse ($summary['low_stock_products']->take(4) as $item)
                                <li class="py-2.5 flex items-center justify-between gap-2">
                                    <span class="font-medium text-slate-800 truncate">{{ $item->name }}</span>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                            {{ $item->inventory->stock_quantity }} {{ $item->inventory->stock_quantity === 1 ? 'unidad' : 'unidades' }}
                                        </span>
                                        <button
                                            @click="openRestockModal('{{ $item->public_id }}', '{{ addslashes($item->name) }}', {{ $item->inventory->stock_quantity }})"
                                            class="rounded bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700 hover:bg-blue-100 transition"
                                            title="Reponer stock"
                                        >
                                            + Reponer
                                        </button>
                                    </div>
                                </li>
                            @empty
                                <li class="py-4 text-center text-slate-400 italic">
                                    Ningún producto con stock bajo actualmente.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </section>

                <!-- Productos Agotados -->
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                Productos agotados
                            </h2>
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">
                                {{ $summary['out_of_stock_products']->count() }}
                            </span>
                        </div>
                        <ul class="mt-3 divide-y divide-slate-100 text-xs">
                            @forelse ($summary['out_of_stock_products']->take(4) as $item)
                                <li class="py-2.5 flex items-center justify-between gap-2">
                                    <span class="font-medium text-slate-800 truncate">{{ $item->name }}</span>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                                            0 unidades
                                        </span>
                                        <button
                                            @click="openRestockModal('{{ $item->public_id }}', '{{ addslashes($item->name) }}', 0)"
                                            class="rounded bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-emerald-700 transition"
                                            title="Reactivar reponiendo stock"
                                        >
                                            + Reponer
                                        </button>
                                    </div>
                                </li>
                            @empty
                                <li class="py-4 text-center text-slate-400 italic">
                                    ¡Excelente! No tienes productos agotados.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </section>

                <!-- Top Productos Más Vendidos -->
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                Más vendidos
                            </h2>
                            <span class="text-[10px] font-semibold text-slate-400">Ranking histórico</span>
                        </div>
                        <ol class="mt-3 divide-y divide-slate-100 text-xs">
                            @forelse ($summary['top_selling_products'] as $index => $item)
                                <li class="py-2.5 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 font-black text-slate-600 text-[10px]">
                                            {{ $index + 1 }}
                                        </span>
                                        <span class="font-medium text-slate-800 truncate">{{ $item->name }}</span>
                                    </div>
                                    <span class="font-bold text-emerald-700 whitespace-nowrap bg-emerald-50 px-2 py-0.5 rounded">
                                        {{ $item->inventory->sold_quantity }} vendidos
                                    </span>
                                </li>
                            @empty
                                <li class="py-4 text-center text-slate-400 italic">
                                    Aún no has registrado ventas.
                                </li>
                            @endforelse
                        </ol>
                    </div>
                </section>
            </div>

            <!-- Tabla Principal de Control de Stock -->
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Control de Stock por Producto</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Registra ventas rápidas, reposiciones de mercancía y ajustes de inventario físico.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('seller.shops.products.create', $shop) }}" class="rounded-md bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-blue-700 transition">
                            + Agregar Producto
                        </a>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase font-semibold text-slate-700">
                            <tr>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3 text-center">Estado Stock</th>
                                <th class="px-4 py-3 text-right">Stock Actual</th>
                                <th class="px-4 py-3 text-right">Vendidos</th>
                                <th class="px-4 py-3 text-right">Precio Venta</th>
                                <th class="px-4 py-3 text-right">Costo Compra</th>
                                <th class="px-4 py-3 text-right">Margen</th>
                                <th class="px-4 py-3 text-right">Acciones Rápidas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($summary['all_products'] as $product)
                                @php
                                    $inv = $product->inventory;
                                    $isControlled = $inv && $inv->track_inventory;
                                @endphp
                                <tr class="hover:bg-slate-50/75 transition">
                                    <!-- Producto -->
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                                @if ($product->images->isNotEmpty())
                                                    <img class="h-full w-full object-cover" src="{{ $product->images->first()->thumbnail_url }}" alt="{{ $product->name }}">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-slate-400">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Zm5-12h.01"/></svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ route('seller.shops.products.edit', [$shop, $product]) }}" class="font-semibold text-slate-900 hover:text-blue-600 transition truncate block">
                                                    {{ $product->name }}
                                                </a>
                                                <span class="text-xs text-slate-400 font-mono">{{ $product->slug }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Estado Stock Badge -->
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        @if (! $isControlled)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                Sin control
                                            </span>
                                        @elseif ($inv->stock_quantity <= 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Agotado
                                            </span>
                                        @elseif ($inv->stock_quantity <= $inv->low_stock_threshold)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Stock bajo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Disponible
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Stock Actual -->
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        @if ($isControlled)
                                            <span class="font-mono text-base font-extrabold {{ $inv->stock_quantity <= 0 ? 'text-rose-600' : ($inv->stock_quantity <= $inv->low_stock_threshold ? 'text-amber-600' : 'text-slate-900') }}">
                                                {{ $inv->stock_quantity }}
                                            </span>
                                            <span class="text-slate-400 text-xs block">mín: {{ $inv->low_stock_threshold }}</span>
                                        @else
                                            <span class="text-xs text-slate-400 italic">Ilimitado</span>
                                        @endif
                                    </td>

                                    <!-- Vendidos -->
                                    <td class="px-4 py-3.5 text-right font-mono font-bold text-emerald-700 whitespace-nowrap">
                                        {{ $isControlled ? $inv->sold_quantity : '-' }}
                                    </td>

                                    <!-- Precio Venta -->
                                    <td class="px-4 py-3.5 text-right font-medium text-slate-900 whitespace-nowrap">
                                        RD$ {{ number_format((float) $product->price, 0) }}
                                    </td>

                                    <!-- Costo Compra (Privado) -->
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        @if ($isControlled && $inv->cost_price !== null)
                                            <span class="font-mono text-xs font-semibold text-slate-700">
                                                RD$ {{ number_format((float) $inv->cost_price, 0) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400 italic">No fijado</span>
                                        @endif
                                    </td>

                                    <!-- Margen -->
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        @if ($isControlled && $inv->cost_price !== null)
                                            <span class="font-mono text-xs font-bold text-violet-700">
                                                RD$ {{ number_format((float) $inv->unit_margin, 0) }}
                                            </span>
                                            @if ($inv->margin_percentage !== null)
                                                <span class="block text-[10px] font-semibold text-slate-500">
                                                    ({{ $inv->margin_percentage }}%)
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </td>

                                    <!-- Acciones Rápidas -->
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($isControlled)
                                                <!-- Registrar Venta -->
                                                <button
                                                    @click="openSaleModal('{{ $product->public_id }}', '{{ addslashes($product->name) }}', {{ $inv->stock_quantity }})"
                                                    class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition {{ $inv->stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                                                    {{ $inv->stock_quantity <= 0 ? 'disabled' : '' }}
                                                    title="Registrar venta directa"
                                                >
                                                    - Venta
                                                </button>

                                                <!-- Reponer -->
                                                <button
                                                    @click="openRestockModal('{{ $product->public_id }}', '{{ addslashes($product->name) }}', {{ $inv->stock_quantity }})"
                                                    class="inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-800 hover:bg-blue-100 transition"
                                                    title="Reponer unidades al stock"
                                                >
                                                    + Reponer
                                                </button>

                                                <!-- Ajustar -->
                                                <button
                                                    @click="openAdjustModal('{{ $product->public_id }}', '{{ addslashes($product->name) }}', {{ $inv->stock_quantity }})"
                                                    class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                                    title="Ajustar stock físico real"
                                                >
                                                    Ajustar
                                                </button>

                                                <!-- Historial de Movimientos -->
                                                <a
                                                    href="{{ route('seller.shops.inventory.movements', [$shop, $product]) }}"
                                                    class="rounded-md border border-slate-200 bg-white p-1 text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition"
                                                    title="Ver bitácora de movimientos"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </a>
                                            @else
                                                <a
                                                    href="{{ route('seller.shops.products.edit', [$shop, $product]) }}"
                                                    class="text-xs font-semibold text-blue-600 hover:text-blue-800 underline underline-offset-2"
                                                >
                                                    Activar control
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-8 text-center text-slate-500" colspan="8">
                                        No hay productos registrados en esta tienda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Bitácora Reciente de Movimientos de la Tienda -->
            @if ($summary['recent_movements']->isNotEmpty())
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="border-b border-slate-200 pb-3">
                        <h2 class="text-sm font-bold text-slate-900">Historial reciente de movimientos</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Últimos movimientos registrados en el inventario de esta tienda.</p>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600">
                            <thead class="bg-slate-50 text-[11px] uppercase font-semibold text-slate-700">
                                <tr>
                                    <th class="px-4 py-2.5">Fecha</th>
                                    <th class="px-4 py-2.5">Producto</th>
                                    <th class="px-4 py-2.5">Tipo</th>
                                    <th class="px-4 py-2.5 text-right">Variación</th>
                                    <th class="px-4 py-2.5 text-right">Stock Anterior</th>
                                    <th class="px-4 py-2.5 text-right">Stock Resultante</th>
                                    <th class="px-4 py-2.5">Nota</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($summary['recent_movements'] as $movement)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="px-4 py-2 font-mono text-slate-500 whitespace-nowrap">
                                            {{ $movement->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-2 font-semibold text-slate-900">
                                            {{ $movement->product?->name ?? 'Producto eliminado' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if ($movement->type === 'sale')
                                                <span class="rounded bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800">Venta</span>
                                            @elseif ($movement->type === 'restock')
                                                <span class="rounded bg-blue-100 px-2 py-0.5 font-bold text-blue-800">Reposición</span>
                                            @else
                                                <span class="rounded bg-amber-100 px-2 py-0.5 font-bold text-amber-800">Ajuste</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono font-bold whitespace-nowrap {{ $movement->quantity > 0 ? 'text-blue-600' : ($movement->quantity < 0 ? 'text-emerald-600' : 'text-slate-500') }}">
                                            {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-slate-500">
                                            {{ $movement->stock_before }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono font-bold text-slate-900">
                                            {{ $movement->stock_after }}
                                        </td>
                                        <td class="px-4 py-2 text-slate-500 italic">
                                            {{ $movement->notes ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <!-- ================= MODAL: REGISTRAR VENTA ================= -->
        <div
            x-show="isSaleModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
            @keydown.escape.window="closeModals()"
        >
            <div
                @click.away="closeModals()"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-all"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Registrar Venta</h3>
                    </div>
                    <button @click="closeModals()" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form :action="saleUrl" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Producto:</p>
                        <p class="text-sm font-bold text-slate-900" x-text="activeProductName"></p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Stock disponible: <span class="font-bold text-slate-800" x-text="activeProductStock"></span> unidades
                        </p>
                    </div>

                    <!-- Selector interactivo: ¿Cantidad vendida? [-] 1 [+] -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">¿Cantidad vendida?</label>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <button
                                type="button"
                                @click="saleQuantity = Math.max(1, saleQuantity - 1)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-xl font-black text-slate-700 hover:bg-slate-200 active:scale-95 transition"
                            >
                                -
                            </button>
                            <input
                                type="number"
                                name="quantity"
                                x-model.number="saleQuantity"
                                min="1"
                                :max="activeProductStock"
                                required
                                class="h-12 w-28 text-center text-2xl font-black text-slate-900 rounded-xl border border-slate-300 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600"
                            >
                            <button
                                type="button"
                                @click="saleQuantity = Math.min(activeProductStock, saleQuantity + 1)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-xl font-black text-slate-700 hover:bg-slate-200 active:scale-95 transition"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600" for="sale_notes">Nota u observación (opcional)</label>
                        <input
                            type="text"
                            name="notes"
                            id="sale_notes"
                            placeholder="Ej: Pedido directo por WhatsApp"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:outline-none"
                        >
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="closeModals()" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-700 shadow-sm transition">
                            Registrar venta
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: REPONER STOCK ================= -->
        <div
            x-show="isRestockModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
            @keydown.escape.window="closeModals()"
        >
            <div
                @click.away="closeModals()"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-all"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Reponer Stock</h3>
                    </div>
                    <button @click="closeModals()" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form :action="restockUrl" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Producto:</p>
                        <p class="text-sm font-bold text-slate-900" x-text="activeProductName"></p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Stock actual: <span class="font-bold text-slate-800" x-text="activeProductStock"></span> unidades
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Cantidad a agregar al stock</label>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <button
                                type="button"
                                @click="restockQuantity = Math.max(1, restockQuantity - 5)"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700 hover:bg-slate-200"
                            >
                                -5
                            </button>
                            <input
                                type="number"
                                name="quantity"
                                x-model.number="restockQuantity"
                                min="1"
                                required
                                class="h-12 w-28 text-center text-2xl font-black text-slate-900 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            >
                            <button
                                type="button"
                                @click="restockQuantity = restockQuantity + 5"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-sm font-bold text-slate-700 hover:bg-slate-200"
                            >
                                +5
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600" for="restock_notes">Nota (opcional)</label>
                        <input
                            type="text"
                            name="notes"
                            id="restock_notes"
                            placeholder="Ej: Llegada de nueva mercancía"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-600 focus:outline-none"
                        >
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="closeModals()" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                            Reponer stock
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: AJUSTAR STOCK ================= -->
        <div
            x-show="isAdjustModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
            @keydown.escape.window="closeModals()"
        >
            <div
                @click.away="closeModals()"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-all"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Ajuste de Stock Físico</h3>
                    </div>
                    <button @click="closeModals()" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form :action="adjustUrl" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Producto:</p>
                        <p class="text-sm font-bold text-slate-900" x-text="activeProductName"></p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Stock registrado en sistema: <span class="font-bold text-slate-800" x-text="activeProductStock"></span> unidades
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Stock real físico</label>
                        <p class="text-[11px] text-slate-500 mt-0.5">Escribe la cantidad exacta que tienes físicamente.</p>
                        <div class="mt-2 flex items-center justify-center">
                            <input
                                type="number"
                                name="new_stock"
                                x-model.number="adjustNewStock"
                                min="0"
                                required
                                class="h-12 w-32 text-center text-2xl font-black text-slate-900 rounded-xl border border-slate-300 focus:border-amber-600 focus:ring-1 focus:ring-amber-600"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600" for="adjust_notes">Motivo del ajuste (opcional)</label>
                        <input
                            type="text"
                            name="notes"
                            id="adjust_notes"
                            placeholder="Ej: Conteo físico de inventario / merma"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-amber-600 focus:outline-none"
                        >
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="closeModals()" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2 text-xs font-bold text-white hover:bg-amber-700 shadow-sm transition">
                            Guardar ajuste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function inventoryManager() {
            return {
                isSaleModalOpen: false,
                isRestockModalOpen: false,
                isAdjustModalOpen: false,
                activeProductId: '',
                activeProductName: '',
                activeProductStock: 0,
                saleQuantity: 1,
                restockQuantity: 5,
                adjustNewStock: 0,
                baseUrl: "{{ url('/panel/tiendas/' . $shop->public_id . '/productos') }}",

                get saleUrl() {
                    return `${this.baseUrl}/${this.activeProductId}/inventario/venta`;
                },
                get restockUrl() {
                    return `${this.baseUrl}/${this.activeProductId}/inventario/reposicion`;
                },
                get adjustUrl() {
                    return `${this.baseUrl}/${this.activeProductId}/inventario/ajuste`;
                },

                openSaleModal(productId, productName, stock) {
                    this.activeProductId = productId;
                    this.activeProductName = productName;
                    this.activeProductStock = stock;
                    this.saleQuantity = 1;
                    this.isSaleModalOpen = true;
                },

                openRestockModal(productId, productName, stock) {
                    this.activeProductId = productId;
                    this.activeProductName = productName;
                    this.activeProductStock = stock;
                    this.restockQuantity = 5;
                    this.isRestockModalOpen = true;
                },

                openAdjustModal(productId, productName, stock) {
                    this.activeProductId = productId;
                    this.activeProductName = productName;
                    this.activeProductStock = stock;
                    this.adjustNewStock = stock;
                    this.isAdjustModalOpen = true;
                },

                closeModals() {
                    this.isSaleModalOpen = false;
                    this.isRestockModalOpen = false;
                    this.isAdjustModalOpen = false;
                }
            };
        }
    </script>
</x-layouts.app>
