@php
    $posPresentation = $presentation['pos'] ?? [];
    $showWholesale = ($posPresentation['show_wholesale'] ?? false) === true;
    $showCredit = ($posPresentation['show_credit'] ?? false) === true;
@endphp

<x-layouts.app :title="'Punto de venta | '.$shop->name">
    <x-admin.header />

    <main
        class="min-h-screen bg-[#f7f7f6] px-4 py-5 text-slate-900 sm:px-6 lg:px-8"
        x-data="webPos(@js($products), @js($customers), @js($clientSaleUuid), @js($posPresentation), @js($posSummary))"
    >
        <div class="mx-auto max-w-[1500px] space-y-5">
            <x-seller.shop-header :shop="$shop" activeTab="pos" />

            @if (session('status'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @foreach (session('bottle_recovery', []) as $recovery)
                <div class="rounded-2xl border px-4 py-3 text-sm {{ ($recovery['covered'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-blue-200 bg-blue-50 text-blue-900' }}">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 text-lg">{{ ($recovery['covered'] ?? false) ? '✓' : '◔' }}</span>
                        <div>
                            <p class="font-black">{{ $recovery['just_covered'] ?? false ? '¡Botella recuperada!' : 'Recuperación de botella' }}</p>
                            <p class="mt-0.5">{{ $recovery['alert'] ?? $recovery['message'] ?? '' }}</p>
                            <p class="mt-1 text-xs font-semibold opacity-80">RD$ {{ number_format((float) ($recovery['revenue'] ?? 0), 2) }} de RD$ {{ number_format((float) ($recovery['cost'] ?? 0), 2) }} · {{ number_format((float) ($recovery['percent'] ?? 0), 1) }}%</p>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-bold">No se pudo registrar la venta.</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-600">Operación</p>
                    <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Nueva venta</h1>
                    <p class="mt-1 text-sm text-slate-500">Selecciona los productos para agregarlos al carrito.</p>
                </div>

                <div class="relative w-full xl:max-w-[480px]">
                    <label class="sr-only" for="pos-search">Buscar producto</label>
                    <input
                        id="pos-search"
                        x-model="search"
                        @keydown.escape="search = ''"
                        type="search"
                        autocomplete="off"
                        class="h-14 w-full rounded-2xl border border-slate-200 bg-white px-12 text-base text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="{{ $posPresentation['search_placeholder'] ?? 'Buscar o escanear producto' }}"
                    >
                    <svg class="pointer-events-none absolute left-4 top-4 h-6 w-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.3-4.3m1.8-5.2a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                    <button x-show="search" x-cloak type="button" @click="search = ''" class="absolute right-4 top-4 text-xs font-bold text-slate-400 hover:text-slate-700">Limpiar</button>
                    <kbd x-show="!search" x-cloak class="pointer-events-none absolute right-4 top-4 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold text-slate-400">F2</kbd>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19V5m0 14h16M8 16v-3m4 3V8m4 8v-6"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Productos disponibles</p>
                        <p class="mt-0.5 text-2xl font-black text-slate-950" x-text="availableProductCount"></p>
                        <p class="text-xs font-semibold text-emerald-600">Listos para vender</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-4-3.5c.7.8 1.8 1.3 4 1.3 2.3 0 4-.8 4-2.4 0-1.5-1.2-2.1-3.9-2.7-2.6-.5-3.9-1.2-3.9-2.8S9.6 5.5 12 5.5c1.9 0 3.1.5 3.8 1.3"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Venta actual</p>
                        <p class="mt-0.5 text-2xl font-black text-slate-950" x-text="money(total)"></p>
                        <p class="text-xs font-semibold text-emerald-600"><span x-text="cart.length"></span> producto(s) en carrito</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.3 4.9 3.2 17a2 2 0 0 0 1.7 3h14.2a2 2 0 0 0 1.7-3L13.7 4.9a2 2 0 0 0-3.4 0Z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500">Productos bajos</p>
                        <p class="mt-0.5 text-2xl font-black text-slate-950" x-text="summary.low_stock"></p>
                        <p class="text-xs font-semibold" :class="summary.low_stock ? 'text-amber-600' : 'text-emerald-600'" x-text="summary.low_stock ? 'Requieren reabastecimiento' : 'Inventario saludable'"></p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('seller.shops.pos.store', $shop) }}" @submit="submitForm($event)" class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_410px]">
                @csrf
                <input type="hidden" name="client_sale_uuid" value="{{ $clientSaleUuid }}">
                <input type="hidden" name="payment_status" :value="paymentStatus">
                <input type="hidden" name="sale_mode" :value="saleMode">
                <input type="hidden" name="credit_amount" :value="computedCreditAmount">
                <input type="hidden" name="discount" :value="discount.toFixed(2)">
                <input type="hidden" :name="paidAmount > 0 ? 'payments[0][method]' : null" :value="paymentMethod">
                <input type="hidden" :name="paidAmount > 0 ? 'payments[0][amount]' : null" :value="paidAmount.toFixed(2)">
                <input type="hidden" :name="paidAmount > 0 && paymentNote.trim() ? 'payments[0][notes]' : null" :value="paymentNote.trim()">

                <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-slate-950">Productos</h2>
                            <p class="mt-1 text-sm text-slate-500"><span x-text="filteredProducts.length"></span> producto(s) encontrados</p>
                        </div>
                        <div class="inline-flex rounded-xl bg-slate-100 p-1" role="group" aria-label="Tipo de precio">
                            <button type="button" @click="saleMode = 'retail'" :class="saleMode === 'retail' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="rounded-lg px-4 py-2 text-xs font-black transition">Detalle</button>
                            @if ($showWholesale)
                                <button type="button" @click="saleMode = 'wholesale'" :class="saleMode === 'wholesale' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="rounded-lg px-4 py-2 text-xs font-black transition">Por mayor</button>
                            @endif
                        </div>
                    </div>

                    <div x-show="hasDecants" x-cloak class="mt-5 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Filtrar por tipo de producto">
                        <button type="button" @click="catalogTab = 'all'" :class="catalogTab === 'all' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-4 py-2 text-xs font-black transition">Todos los tipos</button>
                        <button type="button" @click="catalogTab = 'products'" :class="catalogTab === 'products' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-4 py-2 text-xs font-black transition">Productos</button>
                        <button x-show="hasDecants" x-cloak type="button" @click="catalogTab = 'decants'" :class="catalogTab === 'decants' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-4 py-2 text-xs font-black transition">Decants</button>
                    </div>

                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Filtrar por categoría">
                        <button type="button" @click="selectedCategory = 'all'" :class="selectedCategory === 'all' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-5 py-2.5 text-sm font-bold transition">Todos</button>
                        <template x-for="category in categories" :key="category">
                            <button type="button" @click="selectedCategory = category" :class="selectedCategory === category ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-5 py-2.5 text-sm font-bold transition" x-text="category"></button>
                        </template>
                    </div>

                    @can('update', $shop)
                        <div class="mt-3 flex justify-end">
                            <a href="{{ route('seller.shops.products.create', $shop).'?sale_unit=service' }}" class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-black text-blue-700 transition hover:border-blue-300 hover:bg-blue-100" aria-label="Crear nuevo servicio">
                                <span class="text-base leading-none">＋</span>
                                Servicio
                                <kbd class="rounded-md bg-white px-1.5 py-0.5 text-[10px] font-black text-blue-600 ring-1 ring-blue-200">F4</kbd>
                            </a>
                        </div>
                    @endcan

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        <template x-for="product in filteredProducts" :key="product.id">
                            <article class="group relative flex min-h-[285px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg hover:shadow-blue-100/60">
                                <div class="relative flex h-40 items-center justify-center overflow-hidden bg-slate-50">
                                    <template x-if="product.image_url"><img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain p-3 transition duration-300 group-hover:scale-105"></template>
                                    <template x-if="!product.image_url"><div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-blue-50 text-3xl font-black text-blue-300" x-text="product.name.charAt(0).toUpperCase()"></div></template>
                                    <button type="button" @click="addProduct(product)" :disabled="!canAdd(product)" class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-2xl font-light text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none" :aria-label="`Agregar ${product.name}`">+</button>
                                    <span x-show="product.stock > 0 && product.stock <= 5" x-cloak class="absolute left-3 top-3 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black text-amber-700">Pocas unidades</span>
                                </div>
                                <div class="flex flex-1 flex-col p-4">
                                    <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-400" x-text="product.category"></p>
                                    <h3 class="mt-1 truncate text-sm font-black text-slate-900" x-text="product.name"></h3>
                                    <span x-show="product.is_decant" x-cloak class="mt-1 inline-flex w-fit rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-black text-blue-700" x-text="'DECANT · ' + product.volume_ml + ' ml'"></span>
                                    <span x-show="product.is_service" x-cloak class="mt-1 inline-flex w-fit rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-black text-violet-700">SERVICIO · SIN INVENTARIO</span>
                                    <p class="mt-1 text-lg font-black text-blue-950" x-text="money(priceFor(product))"></p>
                                    <p x-show="product.sale_unit === 'decant' && product.volume_ml" x-cloak class="mt-0.5 text-xs font-bold text-blue-600" x-text="product.volume_ml + ' ml por decant'"></p>
                                    <p x-show="product.is_decant && product.source_product_name" x-cloak class="mt-0.5 truncate text-[11px] text-slate-500" x-text="'Fuente: ' + product.source_product_name"></p>
                                    <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold" :class="stockClass(product)" x-text="stockLabel(product)"></span>
                                        <span class="truncate text-[10px] text-slate-400" x-text="product.code || product.sale_unit_label"></span>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div x-show="!filteredProducts.length" x-cloak class="mt-6 rounded-2xl border border-dashed border-slate-300 p-12 text-center">
                        <p class="font-bold text-slate-700">No encontramos productos</p>
                        <p class="mt-1 text-sm text-slate-500">Prueba otra búsqueda o cambia el filtro de categoría.</p>
                        <button type="button" @click="search = ''; selectedCategory = 'all'" class="mt-4 text-sm font-bold text-blue-600 hover:text-blue-800">Limpiar filtros</button>
                    </div>
                </section>

                <aside class="h-fit overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-5">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div>
                            <h2 class="text-xl font-black text-slate-950">Carrito de venta</h2>
                            <p class="mt-1 text-xs text-slate-500"><span x-text="cartItemCount"></span> unidad(es) seleccionadas</p>
                        </div>
                        <button type="button" @click="clearCart()" :disabled="!cart.length" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 13h8l1-13"/></svg>
                            Vaciar
                        </button>
                    </div>

                    <div class="max-h-[360px] space-y-3 overflow-y-auto px-5 py-4 sm:px-6">
                        <template x-for="(item, index) in cart" :key="item.id">
                            <div class="flex gap-3 border-b border-slate-100 pb-3 last:border-0">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-50">
                                    <template x-if="item.imageUrl"><img :src="item.imageUrl" :alt="item.name" class="h-full w-full object-contain p-1"></template>
                                    <template x-if="!item.imageUrl"><span class="text-lg font-black text-blue-200" x-text="item.name.charAt(0).toUpperCase()"></span></template>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-black text-slate-900" x-text="item.name"></p>
                                            <p class="mt-0.5 text-xs text-slate-500" x-text="(item.saleUnit === 'decant' && item.volumeMl ? item.volumeMl + ' ml · ' : '') + money(item.unitPrice)"></p>
                                        </div>
                                        <button type="button" @click="removeItem(item.id)" class="shrink-0 text-slate-400 transition hover:text-rose-600" aria-label="Quitar producto">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 13h8l1-13"/></svg>
                                        </button>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50">
                                            <button type="button" @click="changeQuantity(item, -1)" class="px-2.5 py-1 text-lg font-bold text-slate-500 transition hover:text-blue-700">−</button>
                                            <span class="min-w-7 text-center text-sm font-black" x-text="item.quantity"></span>
                                            <button type="button" @click="changeQuantity(item, 1)" class="px-2.5 py-1 text-lg font-bold text-slate-500 transition hover:text-blue-700">+</button>
                                        </div>
                                        <span class="text-sm font-black text-slate-950" x-text="money(lineTotal(item))"></span>
                                    </div>
                                </div>
                                <template x-for="(field, fieldIndex) in hiddenFields(item, index)" :key="field.name + fieldIndex">
                                    <input type="hidden" :name="field.name" :value="field.value">
                                </template>
                            </div>
                        </template>
                        <div x-show="!cart.length" x-cloak class="rounded-2xl border border-dashed border-slate-300 px-4 py-12 text-center">
                            <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6m4 13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm9 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>
                            <p class="mt-3 text-sm font-bold text-slate-600">Tu carrito está vacío</p>
                            <p class="mt-1 text-xs text-slate-400">Agrega productos desde la lista.</p>
                        </div>
                    </div>

                    <div x-show="!checkoutOpen" x-cloak class="space-y-4 border-t border-slate-100 px-5 py-5 sm:px-6">
                        <div class="space-y-2 rounded-2xl bg-slate-50 p-4">
                            <div class="flex items-center justify-between text-sm text-slate-500"><span>Subtotal</span><span class="font-bold text-slate-800" x-text="money(subtotal)"></span></div>
                            <div x-show="discount > 0" x-cloak class="flex items-center justify-between text-sm text-rose-600"><span>Descuento</span><span class="font-bold" x-text="'- ' + money(discount)"></span></div>
                            <div class="flex items-end justify-between border-t border-slate-200 pt-3"><span class="text-base font-black text-slate-950">Total</span><span class="text-2xl font-black text-blue-950" x-text="money(total)"></span></div>
                        </div>
                        <button type="button" @click="openCheckout()" :disabled="!cart.length" class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                            Cobrar venta
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>

                    <div x-show="checkoutOpen" x-cloak @click.away="closeCheckout()" @keydown.escape.window="closeCheckout()" role="dialog" aria-modal="true" aria-labelledby="web-pos-checkout-title" class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/65 p-0 backdrop-blur-md sm:items-center sm:p-5">
                        <div @click.stop class="flex max-h-[calc(100dvh-1rem)] w-full max-w-3xl flex-col overflow-hidden rounded-t-[30px] border border-white/80 bg-white shadow-[0_24px_90px_rgba(15,23,42,0.35)] sm:max-h-[calc(100dvh-2.5rem)] sm:rounded-[30px]">
                            <div class="shrink-0 border-b border-slate-100 bg-white px-5 pb-4 pt-5 sm:px-8 sm:pt-6">
                                <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 sm:hidden"></div>
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-blue-600 text-lg text-white shadow-lg shadow-blue-200">$</span>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h2 id="web-pos-checkout-title" class="text-2xl font-black tracking-tight text-slate-950">Cobrar</h2>
                                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-blue-700">Venta</span>
                                                </div>
                                                <p class="mt-1 text-xs font-medium text-slate-500"><span x-text="cartItemCount"></span> artículo(s) · revisa el cobro antes de confirmar</p>
                                            </div>
                                        </div>
                                        <p class="mt-4 text-3xl font-black tabular-nums text-slate-950" x-text="money(total)"></p>
                                    </div>
                                    <button type="button" @click="closeCheckout()" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-2xl leading-none text-slate-500 transition hover:bg-slate-50 hover:text-slate-900" aria-label="Cerrar cobro">&times;</button>
                                </div>
                            </div>

                            <div class="min-h-0 space-y-4 overflow-y-auto px-5 py-5 sm:px-8 sm:py-6">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Detalle de la venta</p>
                                            <p class="mt-1 text-xs text-slate-500">El inventario se actualiza al confirmar.</p>
                                        </div>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-black text-slate-500" x-text="cartItemCount + ' unidad(es)'"></span>
                                    </div>
                                    <div class="max-h-44 space-y-2 overflow-y-auto pr-1">
                                        <template x-for="item in cart" :key="'checkout-' + item.id">
                                            <div class="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5 shadow-sm">
                                                <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                                    <template x-if="item.imageUrl"><img :src="item.imageUrl" :alt="item.name" class="h-full w-full object-contain p-1"></template>
                                                    <template x-if="!item.imageUrl"><span class="text-sm font-black text-blue-300" x-text="item.name.charAt(0).toUpperCase()"></span></template>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-xs font-black text-slate-900" x-text="item.name"></p>
                                                    <p class="mt-0.5 text-[11px] text-slate-500" x-text="item.quantity + ' × ' + money(item.unitPrice)"></p>
                                                </div>
                                                <span class="shrink-0 text-sm font-black tabular-nums text-slate-950" x-text="money(lineTotal(item))"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                @if ($showCredit)
                                    <div>
                                        <div class="mb-2 flex items-center justify-between">
                                            <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-500" for="payment_kind">Forma de cobro</label>
                                            <span class="text-[11px] font-semibold text-slate-400">Selecciona una opción</span>
                                        </div>
                                        <div id="payment_kind" class="grid gap-2 sm:grid-cols-3" role="group" aria-label="Forma de cobro">
                                            <button type="button" @click="paymentKind = 'cash'" :aria-pressed="paymentKind === 'cash'" :class="paymentKind === 'cash' ? 'border-blue-600 bg-blue-50 text-blue-800 ring-2 ring-blue-100' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200'" class="rounded-2xl border px-3 py-3 text-left transition">
                                                <span class="flex items-center gap-2 text-sm font-black"><span class="flex h-7 w-7 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">$</span>Contado</span>
                                                <span class="mt-1 block text-[11px] font-medium text-slate-500">Pago completo ahora</span>
                                            </button>
                                            <button type="button" @click="paymentKind = 'mixed'" :aria-pressed="paymentKind === 'mixed'" :class="paymentKind === 'mixed' ? 'border-blue-600 bg-blue-50 text-blue-800 ring-2 ring-blue-100' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200'" class="rounded-2xl border px-3 py-3 text-left transition">
                                                <span class="flex items-center gap-2 text-sm font-black"><span class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-100 text-amber-700">↔</span>Pago mixto</span>
                                                <span class="mt-1 block text-[11px] font-medium text-slate-500">Una parte queda a crédito</span>
                                            </button>
                                            <button type="button" @click="paymentKind = 'credit'" :aria-pressed="paymentKind === 'credit'" :class="paymentKind === 'credit' ? 'border-blue-600 bg-blue-50 text-blue-800 ring-2 ring-blue-100' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200'" class="rounded-2xl border px-3 py-3 text-left transition">
                                                <span class="flex items-center gap-2 text-sm font-black"><span class="flex h-7 w-7 items-center justify-center rounded-xl bg-violet-100 text-violet-700">◷</span>A crédito</span>
                                                <span class="mt-1 block text-[11px] font-medium text-slate-500">Se cobra después</span>
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <input type="hidden" x-model="paymentKind" value="cash">
                                @endif

                                <div x-show="paymentKind !== 'credit'" x-cloak>
                                    <div class="mb-2 flex items-center justify-between">
                                        <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-500" for="payment_method">Método de pago</label>
                                        <span class="text-[11px] font-semibold text-slate-400">¿Cómo recibes el dinero?</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" role="group" aria-label="Método de pago">
                                        @foreach ($paymentMethods as $method => $config)
                                            @continue($method === 'credit')
                                            <button type="button" @click="paymentMethod = '{{ $method }}'" :aria-pressed="paymentMethod === '{{ $method }}'" :class="paymentMethod === '{{ $method }}' ? 'border-blue-600 bg-blue-50 text-blue-800 ring-2 ring-blue-100' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200'" class="rounded-2xl border px-3 py-3 text-left transition">
                                                <span class="flex items-center gap-2 text-sm font-black"><span class="flex h-7 w-7 items-center justify-center rounded-xl bg-slate-100 text-slate-700">{{ $method === 'cash' ? '$' : ($method === 'card' ? '▣' : ($method === 'bank_transfer' ? '↗' : '…')) }}</span>{{ $config['label'] ?? $method }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                    <input id="payment_method" type="hidden" x-model="paymentMethod">
                                    <div x-show="paymentMethod === 'bank_transfer'" x-cloak class="mt-3 rounded-2xl border border-blue-200 bg-blue-50/70 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div><p class="text-xs font-black uppercase tracking-[0.14em] text-blue-900">Cuentas para transferir</p><p class="mt-1 text-[11px] text-blue-800">Selecciona o comparte estos datos con el cliente.</p></div>
                                            <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-blue-700">{{ $paymentAccounts->count() }} activa(s)</span>
                                        </div>
                                        @forelse ($paymentAccounts as $account)
                                            <div class="mt-3 rounded-xl border border-blue-100 bg-white p-3 text-xs text-slate-700">
                                                <p class="font-black text-slate-900">{{ $account->name }}</p>
                                                <p class="mt-1">{{ $account->bank_name ?: 'Banco no indicado' }} · {{ $account->account_number ?: 'Número no indicado' }}</p>
                                                @if ($account->account_holder)<p class="mt-1">Titular: {{ $account->account_holder }}</p>@endif
                                                @if ($account->instructions)<p class="mt-1 text-slate-500">{{ $account->instructions }}</p>@endif
                                            </div>
                                        @empty
                                            <p class="mt-3 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-amber-700">Configura una cuenta de pago desde Configuración para mostrarla aquí.</p>
                                        @endforelse
                                    </div>
                                </div>

                                <div x-show="paymentKind === 'mixed'" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <label class="text-sm font-black text-amber-950" for="mixed_credit_amount">Monto pendiente a crédito</label>
                                            <p class="mt-0.5 text-xs text-amber-800">El resto se registra como pago inmediato.</p>
                                        </div>
                                        <span class="text-lg font-black text-amber-900" x-text="money(computedCreditAmount)"></span>
                                    </div>
                                    <input id="mixed_credit_amount" x-model.number="mixedCreditAmount" type="number" min="0.01" step="0.01" :max="total" :disabled="paymentKind !== 'mixed'" class="mt-3 w-full rounded-xl border border-amber-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-900" x-text="selectedCustomer ? selectedCustomer.name : 'Cliente general'"></p>
                                            <p class="mt-0.5 text-xs text-slate-500" x-text="selectedCustomer ? (selectedCustomer.balance > 0 ? 'Debe ' + money(selectedCustomer.balance) : 'Sin saldo pendiente') : 'Venta de mostrador' "></p>
                                        </div>
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black" :class="paymentKind !== 'cash' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'" x-text="paymentKind !== 'cash' ? 'Requerido' : 'Opcional'"></span>
                                    </div>
                                    <select id="customer_id" name="customer_id" x-model="customerId" :required="paymentKind !== 'cash'" class="mt-3 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
                                        <option value="">Cliente general · venta de mostrador</option>
                                        <template x-for="customer in customers" :key="customer.id">
                                            <option :value="customer.id" x-text="customer.name + (customer.balance > 0 ? ' · debe ' + money(customer.balance) : '')"></option>
                                        </template>
                                    </select>
                                    <p x-show="!customers.length" x-cloak class="mt-2 text-xs font-semibold text-amber-700">Crea primero un cliente desde Clientes y cobros.</p>
                                </div>

                                <div x-show="paymentKind !== 'cash'" x-cloak class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-500" for="due_date">Vencimiento del crédito <span class="normal-case tracking-normal font-semibold text-slate-400">(opcional)</span></label>
                                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                </div>

                                <div x-show="paymentMethod === 'cash' && paymentKind !== 'credit'" x-cloak class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="text-xs font-black uppercase tracking-[0.14em] text-emerald-900">Recibido</span>
                                            <div class="relative mt-2">
                                                <span class="pointer-events-none absolute left-3 top-2.5 text-sm font-bold text-emerald-700">RD$</span>
                                                <input id="received_amount" x-model.number="receivedAmount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-emerald-200 bg-white py-2.5 pl-12 pr-3 text-lg font-black tabular-nums text-slate-950 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                            </div>
                                        </label>
                                        <div class="rounded-xl bg-white px-3 py-2.5">
                                            <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-900">Devolver</p>
                                            <p class="mt-1 text-2xl font-black tabular-nums" :class="cashChange > 0 ? 'text-emerald-700' : 'text-slate-950'" x-text="money(cashChange)"></p>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button type="button" @click="receivedAmount = total" class="rounded-full border border-emerald-700 bg-white px-4 py-2 text-sm font-black text-emerald-800 transition hover:bg-emerald-100">Exacto</button>
                                        <template x-for="amount in [1000, 2000, 5000]" :key="amount"><button type="button" @click="receivedAmount = amount" class="rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-black text-emerald-800 transition hover:border-emerald-400 hover:bg-emerald-100" x-text="amount.toLocaleString('es-DO')"></button></template>
                                    </div>
                                </div>

                                <div>
                                    <button type="button" @click="showExtras = !showExtras" class="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-black text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                                        <span>Descuento y nota del recibo</span><span class="text-lg text-slate-400" x-text="showExtras ? '−' : '+'"></span>
                                    </button>
                                    <div x-show="showExtras" x-cloak class="grid gap-3 border-x border-b border-slate-200 px-4 pb-4 pt-3 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="text-xs font-black text-slate-700">Descuento</span>
                                            <div class="relative mt-1.5"><span class="pointer-events-none absolute left-3 top-2.5 text-sm font-bold text-slate-400">RD$</span><input id="pos_discount" x-model.number="discount" type="number" min="0" step="0.01" :max="subtotal" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-12 pr-3 text-sm font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100" placeholder="0.00"></div>
                                        </label>
                                        <label class="block"><span class="text-xs font-black text-slate-700">Nota en el recibo</span><input id="payment_note" x-model="paymentNote" type="text" maxlength="255" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100" placeholder="Opcional"></label>
                                    </div>
                                </div>

                                <div class="space-y-2 rounded-2xl bg-slate-950 p-4 text-white">
                                    <div class="flex items-center justify-between text-sm text-slate-300"><span>Subtotal</span><span class="font-bold" x-text="money(subtotal)"></span></div>
                                    <div x-show="discount > 0" x-cloak class="flex items-center justify-between text-sm text-rose-300"><span>Descuento</span><span class="font-bold" x-text="'- ' + money(discount)"></span></div>
                                    <div x-show="computedCreditAmount > 0" x-cloak class="flex items-center justify-between text-sm text-amber-300"><span>Pendiente a crédito</span><span class="font-bold" x-text="money(computedCreditAmount)"></span></div>
                                    <div class="flex items-end justify-between border-t border-white/15 pt-3"><span class="text-base font-black">Total</span><span class="text-2xl font-black tabular-nums" x-text="money(total)"></span></div>
                                    <p x-show="paidAmount > 0 && computedCreditAmount > 0" x-cloak class="text-right text-xs font-semibold text-emerald-300">Pago ahora: <span x-text="money(paidAmount)"></span></p>
                                </div>

                                <p x-show="formError" x-text="formError" class="text-xs font-bold text-rose-600"></p>
                                <button type="submit" :disabled="!cart.length || submitting" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-4 text-sm font-black text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                                    <span x-show="!submitting" x-text="paymentKind === 'credit' ? 'Registrar venta a crédito' : 'Confirmar venta · ' + money(total)"></span>
                                    <span x-show="submitting" x-cloak>Registrando...</span>
                                    <svg x-show="!submitting" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </main>

    <script>
        function webPos(products, customers, clientSaleUuid, presentation, summary) {
            return {
                products,
                customers,
                clientSaleUuid,
                presentation,
                summary,
                cart: [],
                search: '',
                catalogTab: 'all',
                selectedCategory: 'all',
                saleMode: 'retail',
                paymentKind: 'cash',
                paymentMethod: 'cash',
                customerId: '',
                mixedCreditAmount: 0,
                discount: 0,
                paymentNote: '',
                receivedAmount: 0,
                checkoutOpen: false,
                submitting: false,
                formError: '',
                showExtras: false,

                get categories() {
                    return [...new Set(this.products.map((product) => product.category).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
                },

                get hasDecants() {
                    return this.products.some((product) => product.is_decant);
                },

                get filteredProducts() {
                    const query = this.search.trim().toLowerCase();
                    return this.products.filter((product) => {
                        if (this.saleMode === 'wholesale' && product.wholesale_price === null) return false;
                        if (this.catalogTab === 'products' && product.is_decant) return false;
                        if (this.catalogTab === 'decants' && !product.is_decant) return false;
                        if (this.selectedCategory !== 'all' && product.category !== this.selectedCategory) return false;
                        if (!query) return true;
                        return [product.name, product.code, product.brand, ...(product.attributes || []), product.category, product.sale_unit_label]
                            .filter(Boolean)
                            .some((value) => String(value).toLowerCase().includes(query));
                    });
                },

                get availableProductCount() {
                    return this.products.filter((product) => product.is_service || product.stock > 0).length;
                },

                get cartItemCount() {
                    return this.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                },

                get selectedCustomer() {
                    return this.customers.find((customer) => String(customer.id) === String(this.customerId)) || null;
                },

                get subtotal() {
                    return this.cart.reduce((sum, item) => sum + this.lineTotal(item), 0);
                },

                get total() {
                    return Math.max(0, this.subtotal - Math.max(0, Number(this.discount) || 0));
                },

                get paymentStatus() {
                    if (this.paymentKind === 'credit') return 'pending';
                    if (this.paymentKind === 'mixed') return 'partial';
                    return 'paid';
                },

                get cashChange() {
                    if (this.paymentKind !== 'cash' || this.paymentMethod !== 'cash') return 0;
                    return Math.max(0, (Number(this.receivedAmount) || this.total) - this.total);
                },

                get computedCreditAmount() {
                    if (this.paymentKind === 'credit') return this.total;
                    if (this.paymentKind === 'mixed') return Math.max(0, Math.min(this.total, Number(this.mixedCreditAmount) || 0));
                    return 0;
                },

                get paidAmount() {
                    return Math.max(0, this.total - this.computedCreditAmount);
                },

                priceFor(product) {
                    return this.saleMode === 'wholesale' ? product.wholesale_price : product.price;
                },

                stockClass(product) {
                    if (product.is_service) return 'bg-violet-50 text-violet-700';
                    if (product.stock <= 0) return 'bg-rose-50 text-rose-700';
                    if (product.stock <= 5) return 'bg-amber-50 text-amber-700';
                    return 'bg-emerald-50 text-emerald-700';
                },

                stockLabel(product) {
                    if (product.is_service) return 'Servicio · sin inventario';
                    if (product.stock <= 0) return 'Agotado';
                    return `En stock (${product.stock})`;
                },

                canAdd(product) {
                    return (product.is_service || product.stock > 0) && !(this.saleMode === 'wholesale' && product.wholesale_price === null);
                },

                addProduct(product) {
                    if (!this.canAdd(product)) return;
                    const existing = this.cart.find((item) => item.id === product.id);
                    if (existing) {
                        existing.quantity = product.is_service ? existing.quantity + 1 : Math.min(product.stock, existing.quantity + 1);
                        return;
                    }
                    this.cart.push({
                        id: product.id,
                        name: product.name,
                        imageUrl: product.image_url,
                        quantity: 1,
                        stock: product.stock,
                        isService: product.is_service,
                        unitPrice: this.priceFor(product),
                        saleUnit: product.sale_unit,
                        saleUnitLabel: product.sale_unit_label,
                        volumeMl: product.volume_ml,
                        sourceProductId: product.source_product_id,
                        sourceProductName: product.source_product_name,
                    });
                    this.formError = '';
                },

                changeQuantity(item, delta) {
                    item.quantity = item.isService ? Math.max(1, item.quantity + delta) : Math.max(1, Math.min(item.stock, item.quantity + delta));
                },

                removeItem(id) {
                    this.cart = this.cart.filter((item) => item.id !== id);
                },

                clearCart() {
                    this.cart = [];
                    this.discount = 0;
                    this.paymentNote = '';
                    this.receivedAmount = 0;
                    this.formError = '';
                    this.showExtras = false;
                },

                openCheckout() {
                    if (!this.cart.length) {
                        this.formError = 'Agrega al menos un producto.';
                        return;
                    }
                    this.formError = '';
                    this.receivedAmount = this.total;
                    this.checkoutOpen = true;
                },

                closeCheckout() {
                    if (!this.submitting) this.checkoutOpen = false;
                },

                repriceCart() {
                    this.cart = this.cart.filter((item) => {
                        const product = this.products.find((candidate) => candidate.id === item.id);
                        if (!product || !this.canAdd(product)) return false;
                        item.unitPrice = this.priceFor(product);
                        item.stock = product.stock;
                        item.isService = product.is_service;
                        item.quantity = product.is_service ? item.quantity : Math.min(item.quantity, product.stock);
                        return product.is_service || product.stock > 0;
                    });
                },

                lineTotal(item) {
                    return Number(item.unitPrice || 0) * Number(item.quantity || 0);
                },

                money(value) {
                    return 'RD$ ' + Number(value || 0).toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                hiddenFields(item, index) {
                    return [
                        { name: `items[${index}][product_id]`, value: item.id },
                        { name: `items[${index}][quantity]`, value: item.quantity },
                        { name: `items[${index}][unit_price]`, value: Number(item.unitPrice).toFixed(2) },
                        { name: `items[${index}][expected_sale_unit]`, value: item.saleUnit },
                        { name: `items[${index}][expected_volume_ml]`, value: item.volumeMl || '' },
                        { name: `items[${index}][expected_source_product_id]`, value: item.sourceProductId || '' },
                    ];
                },

                submitForm(event) {
                    if (!this.cart.length) {
                        event.preventDefault();
                        this.formError = 'Agrega al menos un producto.';
                        return;
                    }
                    if (this.paymentKind !== 'cash' && !this.customerId) {
                        event.preventDefault();
                        this.formError = 'Selecciona un cliente para registrar el crédito.';
                        return;
                    }
                    if (this.total <= 0) {
                        event.preventDefault();
                        this.formError = 'El total debe ser mayor que cero.';
                        return;
                    }
                    if (this.paymentKind === 'cash' && this.paymentMethod === 'cash' && (Number(this.receivedAmount) || 0) < this.total) {
                        event.preventDefault();
                        this.formError = 'El efectivo recibido no cubre el total de la venta.';
                        return;
                    }
                    this.submitting = true;
                },

                init() {
                    this.$watch('saleMode', () => this.repriceCart());
                    this.$watch('paymentKind', (kind) => {
                        if (kind === 'credit') this.mixedCreditAmount = this.total;
                        if (kind === 'cash') this.mixedCreditAmount = 0;
                    });
                    this.$watch('total', (value) => {
                        if (!this.receivedAmount || Number(this.receivedAmount) < value) this.receivedAmount = value;
                        if (Number(this.mixedCreditAmount) > value) this.mixedCreditAmount = value;
                    });
                    this.$watch('cart', () => { this.formError = ''; }, { deep: true });
                },
            };
        }
    </script>
</x-layouts.app>
