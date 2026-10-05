<x-layouts.app :title="'Control del Negocio y Ganancias | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Negocio y Ganancias']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <!-- Shop Context & Local Navigation Tabs -->
            <x-seller.shop-header :shop="$shop" activeTab="business" />

            <!-- Flash Status & Error Messages -->
            @if (session('status'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900 shadow-xs flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-900 shadow-xs">
                    <div class="flex items-center gap-2 mb-2 font-bold text-rose-950">
                        <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Se encontraron los siguientes problemas:</span>
                    </div>
                    <ul class="list-disc pl-5 space-y-1 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Page Header & Action Bar -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700 font-black text-base shadow-2xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </span>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                            {{ $shop->name }} · Control del negocio
                        </h1>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Monitoreo financiero, margen de ganancia por producto (FIFO), lotes de mercancía y pedidos pendientes.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('seller.shops.inventory.index', $shop) }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition"
                    >
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <span>Ventas e inventario</span>
                    </a>

                    <a
                        href="{{ route('seller.shops.customers.index', $shop) }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition"
                    >
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1m-4 6H3v-2a4 4 0 014-4h6a4 4 0 014 4v2zM10 10a4 4 0 100-8 4 4 0 000 8z"/>
                        </svg>
                        <span>Clientes & Cobros</span>
                    </a>

                    <a
                        href="{{ route('shops.show', $shop) }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 transition"
                    >
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        <span>Ver vitrina ↗</span>
                    </a>
                </div>
            </div>

            <!-- Date Range Filter Toolbar -->
            <form method="GET" action="{{ route('seller.shops.business', $shop) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Desde</label>
                            <input
                                type="date"
                                name="from"
                                value="{{ $from }}"
                                class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-semibold text-slate-900 shadow-2xs focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Hasta</label>
                            <input
                                type="date"
                                name="to"
                                value="{{ $to }}"
                                class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-semibold text-slate-900 shadow-2xs focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition"
                            >
                        </div>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 focus:ring-2 focus:ring-blue-500/20 transition cursor-pointer"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            <span>Consultar</span>
                        </button>

                        @if (request()->has('from') || request()->has('to'))
                            <a href="{{ route('seller.shops.business', $shop) }}" class="text-xs font-bold text-slate-500 hover:text-slate-800 underline transition">
                                Restablecer
                            </a>
                        @endif
                    </div>

                    <div class="text-xs text-slate-500 flex items-center gap-2">
                        <span>Rango activo:</span>
                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-800 tabular-nums">
                            {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            </form>

            <!-- 5 KPI Summary Cards Grid (Rule 8: Zero Distortion on Currency & Identifiers) -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <!-- 1. Ventas Hoy -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Ventas hoy</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <!-- Rule 8: Dedicated line with tabular-nums and whitespace-nowrap -->
                    <p class="mt-3 text-2xl font-black text-emerald-700 tracking-tight tabular-nums whitespace-nowrap">
                        RD$ {{ number_format($today, 2) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">Facturado hoy (neto)</p>
                </div>

                <!-- 2. Ventas del Período -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Ventas del período</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                    </div>
                    <!-- Rule 8 -->
                    <p class="mt-3 text-2xl font-black text-blue-700 tracking-tight tabular-nums whitespace-nowrap">
                        RD$ {{ number_format($total, 2) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        @if ($discount > 0)
                            Dctos: <span class="font-bold text-slate-700 tabular-nums">RD$ {{ number_format($discount, 2) }}</span>
                        @else
                            Total en el rango de fechas
                        @endif
                    </p>
                </div>

                <!-- 3. Cuentas por Cobrar -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Por cobrar</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1m-4 6H3v-2a4 4 0 014-4h6a4 4 0 014 4v2zM10 10a4 4 0 100-8 4 4 0 000 8z"/>
                            </svg>
                        </div>
                    </div>
                    <!-- Rule 8 -->
                    <p class="mt-3 text-2xl font-black text-slate-900 tracking-tight tabular-nums whitespace-nowrap">
                        RD$ {{ number_format($receivable, 2) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">Créditos pendientes</p>
                </div>

                <!-- 4. Stock Bajo -->
                <div class="rounded-2xl border {{ $inventory['low_stock_count'] > 0 ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200 bg-white' }} p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider {{ $inventory['low_stock_count'] > 0 ? 'text-amber-800' : 'text-slate-500' }}">Stock bajo</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl {{ $inventory['low_stock_count'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-black {{ $inventory['low_stock_count'] > 0 ? 'text-amber-700' : 'text-slate-900' }} tabular-nums">
                        {{ $inventory['low_stock_count'] }}
                    </p>
                    <p class="mt-1 text-[11px] {{ $inventory['low_stock_count'] > 0 ? 'text-amber-800 font-semibold' : 'text-slate-500' }}">Por agotarse</p>
                </div>

                <!-- 5. Agotados -->
                <div class="rounded-2xl border {{ $inventory['out_of_stock_count'] > 0 ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider {{ $inventory['out_of_stock_count'] > 0 ? 'text-rose-800' : 'text-slate-500' }}">Agotados</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl {{ $inventory['out_of_stock_count'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-black {{ $inventory['out_of_stock_count'] > 0 ? 'text-rose-700' : 'text-slate-900' }} tabular-nums">
                        {{ $inventory['out_of_stock_count'] }}
                    </p>
                    <p class="mt-1 text-[11px] {{ $inventory['out_of_stock_count'] > 0 ? 'text-rose-800 font-semibold' : 'text-slate-500' }}">Sin existencias</p>
                </div>
            </section>

            <!-- SECTION 1: Ventas y Ganancia por Producto -->
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 font-bold">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-base font-black text-slate-900">Ventas y ganancia por producto</h2>
                            <p class="text-xs text-slate-500">Rendimiento por artículo en el período seleccionado.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 tabular-nums">
                        {{ $products->count() }} {{ $products->count() === 1 ? 'producto vendido' : 'productos vendidos' }}
                    </span>
                </div>

                <!-- Explanation Callout -->
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-xs text-slate-600 space-y-1 leading-relaxed">
                    <p>
                        <strong class="text-slate-800">Cálculo FIFO:</strong> Ganancia bruta calculada antes de gastos operativos y comisiones. Impuestos excluidos; descuentos generales prorrateados al centavo. Las devoluciones se descuentan en la fecha del reembolso; solo la mercancía repuesta recupera su costo de inventario.
                    </p>
                    @if ($discount > 0)
                        <p class="font-bold text-slate-700">
                            Descuentos generales del período: <span class="tabular-nums">RD$ {{ number_format($discount, 2) }}</span>
                        </p>
                    @endif
                </div>

                <!-- Products Table -->
                <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3">Producto</th>
                                <th scope="col" class="px-4 py-3 text-right">Vendidos</th>
                                <th scope="col" class="px-4 py-3 text-right">Ingreso neto</th>
                                <th scope="col" class="px-4 py-3 text-right">Costo FIFO</th>
                                <th scope="col" class="px-4 py-3 text-right">Ganancia bruta</th>
                                <th scope="col" class="px-4 py-3 text-center">Margen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($products as $row)
                                @php
                                    $grossProfit = $row->unknown_lines ? null : ($row->revenue - $row->known_cost);
                                    $marginPercent = ($row->revenue > 0 && ! $row->unknown_lines)
                                        ? (($grossProfit / $row->revenue) * 100)
                                        : null;
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-4 py-3.5 font-bold text-slate-900">
                                        {{ $row->product_name }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-black text-slate-800 tabular-nums whitespace-nowrap">
                                        {{ number_format($row->units) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                        RD$ {{ number_format($row->revenue, 2) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-semibold text-slate-700 tabular-nums whitespace-nowrap">
                                        @if ($row->unknown_lines)
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 border border-amber-200">
                                                Costo incompleto
                                            </span>
                                        @else
                                            RD$ {{ number_format($row->known_cost, 2) }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right tabular-nums whitespace-nowrap">
                                        @if ($row->unknown_lines)
                                            <span class="text-slate-400 font-medium">No calculable: faltan costos</span>
                                        @else
                                            <span class="font-black {{ $grossProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                RD$ {{ number_format($grossProfit, 2) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        @if ($marginPercent !== null)
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black tabular-nums {{ $marginPercent >= 40 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($marginPercent > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                                                {{ number_format($marginPercent, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="h-8 w-8 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                            </svg>
                                            <p class="font-medium">Sin ventas en este período.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @can('update', $shop)
                <!-- SECTION 2: Lotes de Inventario (FIFO) -->
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600 font-bold">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-base font-black text-slate-900">Lotes de inventario</h2>
                                <p class="text-xs text-slate-500">Historial y saldos de mercancía registrada por orden de entrada.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 tabular-nums">
                            {{ $lots->count() }} {{ $lots->count() === 1 ? 'lote' : 'lotes registrados' }}
                        </span>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Los saldos iniciales usan el costo registrado al comenzar FIFO; no reconstruyen compras anteriores.
                    </p>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Producto</th>
                                    <th scope="col" class="px-4 py-3 text-right">Recibido</th>
                                    <th scope="col" class="px-4 py-3 text-right">Restante</th>
                                    <th scope="col" class="px-4 py-3 text-right">Costo recibido</th>
                                    <th scope="col" class="px-4 py-3">Origen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($lots as $lot)
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-4 py-3 font-bold text-slate-900">
                                            {{ $inventory['all_products']->firstWhere('id', $lot->product_id)?->name ?? 'Producto #' . $lot->product_id }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-slate-800 tabular-nums whitespace-nowrap">
                                            {{ $lot->received_quantity }} {{ $lot->quantity_unit }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-black tabular-nums whitespace-nowrap {{ $lot->remaining_quantity > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                            {{ $lot->remaining_quantity }} {{ $lot->quantity_unit }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                            {{ $lot->received_cost_cents === null ? 'Desconocido' : 'RD$ '.number_format($lot->received_cost_cents / 100, 2) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 capitalize">
                                                {{ $lot->origin }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                                            Sin lotes de inventario registrados.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- SECTION 3: Reglas de Precio Automáticas · Pro -->
                <section class="rounded-2xl border border-indigo-200 bg-white p-6 shadow-xs">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-indigo-100 pb-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 font-bold">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-black text-slate-900">Reglas de precio · Pro</h2>
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-700 tracking-wider">
                                        Pro
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">Ajuste automático de margen sobre costo al recibir mercancía.</p>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Margen sobre precio de venta y redondeo hacia arriba. Las bajadas siempre necesitan aprobación.
                    </p>

                    <div class="mt-5 space-y-4">
                        @foreach ($inventory['all_products']->where('sale_unit', '!=', 'decant') as $product)
                            @php($rule = $rules->get($product->id))
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 transition hover:border-slate-300">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-sm font-bold text-slate-900">
                                        {{ $product->name }} · <span class="font-black text-slate-800 tabular-nums">RD$ {{ number_format($product->price, 2) }}</span>
                                    </h3>
                                </div>

                                <form class="mt-3 flex flex-wrap items-center gap-3 text-xs" method="POST" action="{{ route('seller.shops.pricing.rule', [$shop, $product]) }}">
                                    @csrf
                                    <label class="flex items-center gap-1.5 font-bold text-slate-700">
                                        <span>Margen %:</span>
                                        <input
                                            class="w-20 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-900 shadow-2xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="margin_percent"
                                            type="number"
                                            min="0"
                                            max="95"
                                            step="0.01"
                                            value="{{ $rule->margin_percent ?? 40 }}"
                                            required
                                        >
                                    </label>

                                    <label class="flex items-center gap-1.5 font-bold text-slate-700">
                                        <span>Redondear a RD$:</span>
                                        <input
                                            class="w-24 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-900 shadow-2xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="round_step"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value="{{ ($rule->round_step_cents ?? 100) / 100 }}"
                                            required
                                        >
                                    </label>

                                    <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-slate-700">
                                        <input
                                            name="auto_increase"
                                            type="checkbox"
                                            value="1"
                                            @checked($rule?->auto_increase)
                                            class="rounded text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span>Aplicar subidas automáticamente</span>
                                    </label>

                                    <button
                                        type="submit"
                                        class="rounded-lg bg-blue-700 px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs hover:bg-blue-800 transition cursor-pointer"
                                    >
                                        Guardar regla
                                    </button>
                                </form>

                                @if ($rule?->pending_price !== null)
                                    <form class="mt-3 flex items-center gap-2" method="POST" action="{{ route('seller.shops.pricing.approve', [$shop, $product]) }}">
                                        @csrf
                                        <input name="expected_price" type="hidden" value="{{ $rule->pending_price }}">
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-900 shadow-2xs hover:bg-amber-200 transition cursor-pointer"
                                        >
                                            <span>⚠️ Aprobar precio propuesto:</span>
                                            <span class="font-black tabular-nums">RD$ {{ number_format($rule->pending_price, 2) }}</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endcan

            <!-- SECTION 4: Pedidos pendientes de confirmar -->
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600 font-bold">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-base font-black text-slate-900">Pedidos pendientes de confirmar</h2>
                            <p class="text-xs text-slate-500">Órdenes recibidas que requieren cobro y confirmación de despacho.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 tabular-nums">
                        {{ $orders->total() }} {{ $orders->total() === 1 ? 'pedido pendiente' : 'pedidos pendientes' }}
                    </span>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    El pedido no reserva existencias. Confirma solo después de acordar y cobrar la venta.
                </p>

                <div class="mt-5 space-y-3">
                    @forelse ($orders as $order)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 transition hover:border-slate-300">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-black text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md px-2 py-0.5">
                                            {{ $order->order_number }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-800">
                                            {{ $order->customer_name }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-600">
                                        {{ $order->items->map(fn($item) => $item->quantity.' × '.$item->product_name)->join(', ') }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-[10px] uppercase font-bold text-slate-500">Total a cobrar</p>
                                        <p class="text-sm font-black text-slate-900 tabular-nums whitespace-nowrap">
                                            RD$ {{ number_format($order->total, 2) }}
                                        </p>
                                    </div>

                                    <form method="POST" action="{{ route('seller.shops.orders.confirm', [$shop, $order]) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition cursor-pointer"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span>Confirmar venta cobrada</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center">
                            <svg class="mx-auto h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="mt-2 text-xs font-semibold text-slate-500">Sin pedidos pendientes.</p>
                        </div>
                    @endforelse
                </div>

                @if ($orders->hasPages())
                    <div class="mt-6 border-t border-slate-100 pt-4">
                        {{ $orders->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
