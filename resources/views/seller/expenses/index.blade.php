<x-layouts.app :title="'Gastos Operativos | ' . $shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Gastos operativos']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <x-seller.shop-header :shop="$shop" activeTab="expenses" />

            <!-- Flash Status & Errors -->
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
                        <span>Atención:</span>
                    </div>
                    <ul class="list-disc pl-5 space-y-1 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Title & Top Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-100 text-rose-800 font-black text-lg shadow-2xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                            </svg>
                        </span>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Gastos operativos</h1>
                            <p class="mt-0.5 text-xs text-slate-500">Registro de costos fijos, variables y servicios que se deducen de tu ganancia bruta.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('seller.shops.cash.index', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>Control de caja</span>
                    </a>
                    <a href="{{ route('seller.shops.business', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>Resumen del negocio ↗</span>
                    </a>
                </div>
            </div>

            <!-- Total and Category Breakdown Banner -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-xs">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total gastado en período</span>
                    <p class="mt-2 text-2xl font-black text-rose-700 tracking-tight tabular-nums whitespace-nowrap">
                        RD$ {{ number_format($totalPeriod, 2) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Deducción directa de la ganancia operativa.
                    </p>
                </div>

                <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Desglose por categoría</span>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($byCategory as $cat)
                            <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-800">
                                <span>{{ $cat->cat_name }}:</span>
                                <strong class="font-black tabular-nums text-slate-900">RD$ {{ number_format($cat->cat_cents / 100.0, 2) }}</strong>
                            </span>
                        @empty
                            <span class="text-xs text-slate-400">Sin gastos registrados en el rango seleccionado.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- REGISTRATION FORMS -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Register Expense Form -->
                <div class="lg:col-span-2 rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                    <h2 class="text-base font-black text-slate-900">Registrar nuevo gasto</h2>

                    <form method="POST" action="{{ route('seller.shops.expenses.store', $shop) }}" class="space-y-4 text-xs">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Categoría</label>
                                <select name="expense_category_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-rose-600">
                                    <option value="">Selecciona una categoría...</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Monto (RD$)</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-black text-slate-900 tabular-nums focus:border-rose-600">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Descripción del gasto</label>
                                <input type="text" name="description" required placeholder="Ej: Pago de luz local comercial, Transporte pedido #45" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-medium text-slate-900 focus:border-rose-600">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Método de pago</label>
                                <select name="payment_method" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-rose-600">
                                    @foreach ($paymentMethods as $mKey => $mLabel)
                                        <option value="{{ $mKey }}">{{ $mLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Fecha del gasto</label>
                                <input type="date" name="occurred_at" value="{{ now()->toDateString() }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-rose-600">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Comprobante / NCF / Referencia (opcional)</label>
                                <input type="text" name="reference" placeholder="Ej: B0100000045, Factura #123" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-medium text-slate-900 focus:border-rose-600">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Notas adicionales (opcional)</label>
                            <input type="text" name="notes" placeholder="Detalles u observaciones" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-medium text-slate-900 focus:border-rose-600">
                        </div>

                        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-3 text-xs text-blue-800 flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Si seleccionas <strong>Efectivo</strong> y tu caja está abierta, el importe se descontará automáticamente del arqueo de caja.</span>
                        </div>

                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-6 py-3 text-xs font-bold text-white shadow-xs hover:bg-rose-700 transition cursor-pointer">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Registrar gasto</span>
                        </button>
                    </form>
                </div>

                <!-- Add Category Sidebar Form -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                    <h2 class="text-base font-black text-slate-900">Nueva categoría</h2>
                    <p class="text-xs text-slate-500">Agrega categorías personalizadas para clasificar mejor los gastos de tu negocio.</p>

                    <form method="POST" action="{{ route('seller.shops.expenses.categories.store', $shop) }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nombre de categoría</label>
                            <input type="text" name="name" required placeholder="Ej: Empaques, Seguridad, Combustible" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-slate-800">
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                            <span>Crear categoría</span>
                        </button>
                    </form>

                    <div class="border-t border-slate-100 pt-3">
                        <span class="text-[10px] uppercase font-bold text-slate-500">Categorías activas</span>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($categories as $c)
                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ $c->name }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXPENSES LIST TABLE -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <h2 class="text-base font-black text-slate-900">Historial de gastos</h2>

                    <!-- Date & Filter Form -->
                    <form method="GET" action="{{ route('seller.shops.expenses.index', $shop) }}" class="flex flex-wrap items-center gap-2 text-xs">
                        <input type="date" name="from" value="{{ $from }}" class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold">
                        <input type="date" name="to" value="{{ $to }}" class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold">
                        <select name="category_id" class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold">
                            <option value="">Todas las categorías</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-xl bg-slate-800 px-3 py-1.5 font-bold text-white hover:bg-slate-900 cursor-pointer">Filtrar</button>
                    </form>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Categoría</th>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3">Método</th>
                                <th class="px-4 py-3">Referencia</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($expenses as $expense)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 text-slate-600 font-mono">
                                        {{ $expense->occurred_at->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-800">
                                        {{ $expense->category?->name ?? 'Sin categoría' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-900 font-medium">
                                        {{ $expense->description }}
                                        @if ($expense->notes)
                                            <span class="block text-[11px] text-slate-400">{{ $expense->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 capitalize">
                                            {{ $paymentMethods[$expense->payment_method] ?? $expense->payment_method }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 font-mono text-[11px]">
                                        {{ $expense->reference ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-rose-700 tabular-nums whitespace-nowrap">
                                        RD$ {{ number_format($expense->amount_cents / 100.0, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                        Sin gastos registrados en el período seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($expenses->hasPages())
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        {{ $expenses->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
