<x-layouts.app :title="'Reglas de Precio · Pro | ' . $shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Productos', 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Reglas de precio']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <x-seller.shop-header :shop="$shop" activeTab="products" />

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
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-800 font-black text-lg shadow-2xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Reglas de precio automáticas</h1>
                                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-indigo-700 tracking-wider">
                                    Pro
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">Ajuste automático de margen sobre costo al recibir mercancía nueva.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('seller.shops.products.index', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>← Volver a Productos</span>
                    </a>
                    <a href="{{ route('seller.shops.business', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>Resumen del negocio ↗</span>
                    </a>
                </div>
            </div>

            <!-- Rules Grid -->
            <section class="rounded-3xl border border-indigo-200 bg-white p-6 shadow-xs space-y-4">
                <div class="border-b border-indigo-100 pb-4">
                    <h2 class="text-base font-black text-slate-900">Configuración de margen por producto</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Define el margen deseado sobre el precio de venta y el paso de redondeo. Si el costo del lote recibido incrementa, el precio puede subir automáticamente o quedar pendiente de tu aprobación manual.
                    </p>
                </div>

                <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-700">Regla global</p>
                            <h2 class="mt-1 text-base font-black text-slate-900">Configura todos los productos de una vez</h2>
                            <p class="mt-1 max-w-2xl text-xs leading-5 text-blue-900/70">No cambia precios ahora. Solo deja la misma regla preparada para los próximos costos recibidos.</p>
                        </div>
                        <form method="POST" action="{{ route('seller.shops.pricing.bulk-rule', $shop) }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @csrf
                            <input type="hidden" name="scope" value="all">
                            <label class="text-xs font-bold text-slate-700">Margen %<input name="margin_percent" type="number" min="0" max="95" step="0.01" value="40" required class="mt-1 w-full rounded-lg border border-blue-200 bg-white px-2.5 py-2 text-xs"></label>
                            <label class="text-xs font-bold text-slate-700">Redondear a RD$<input name="round_step" type="number" min="0.01" step="0.01" value="1.00" required class="mt-1 w-full rounded-lg border border-blue-200 bg-white px-2.5 py-2 text-xs"></label>
                            <label class="flex items-center gap-2 self-end pb-2 text-xs font-semibold text-slate-700"><input name="auto_increase" type="checkbox" value="1" class="rounded text-blue-600"> Aplicar subidas automáticamente</label>
                            <button class="self-end rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Guardar regla global</button>
                        </form>
                    </div>
                </div>

                <div class="space-y-4 pt-2">
                    @forelse ($products as $product)
                        @php($rule = $rules->get($product->id))
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 transition hover:border-slate-300">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900">
                                    {{ $product->name }} · <span class="font-black text-slate-800 tabular-nums">RD$ {{ number_format($product->price, 2) }}</span>
                                    @if ($product->inventory?->cost_price)
                                        <span class="text-xs text-slate-500 font-normal"> (Costo actual: RD$ {{ number_format($product->inventory->cost_price, 2) }})</span>
                                    @endif
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
                    @empty
                        <p class="text-xs text-slate-400 py-4 text-center">Sin productos disponibles para configurar reglas.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</x-layouts.app>
