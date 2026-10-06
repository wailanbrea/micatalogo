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
                        placeholder="Buscar o escanear producto"
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
                <input type="hidden" name="payment_status" value="paid">
                <input type="hidden" name="sale_mode" :value="saleMode">
                <input type="hidden" name="credit_amount" :value="computedCreditAmount">
                <input type="hidden" name="payments[0][method]" :value="paymentMethod">
                <input type="hidden" name="payments[0][amount]" :value="paidAmount.toFixed(2)">

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

                    <div class="mt-5 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Filtrar por categoría">
                        <button type="button" @click="selectedCategory = 'all'" :class="selectedCategory === 'all' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-5 py-2.5 text-sm font-bold transition">Todos</button>
                        <template x-for="category in categories" :key="category">
                            <button type="button" @click="selectedCategory = category" :class="selectedCategory === category ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-5 py-2.5 text-sm font-bold transition" x-text="category"></button>
                        </template>
                    </div>

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
                                    <p class="mt-1 text-lg font-black text-blue-950" x-text="money(priceFor(product))"></p>
                                    <p x-show="product.sale_unit === 'decant' && product.volume_ml" x-cloak class="mt-0.5 text-xs font-bold text-blue-600" x-text="product.volume_ml + ' ml por decant'"></p>
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

                    <div class="space-y-4 border-t border-slate-100 px-5 py-5 sm:px-6">
                        @if ($showCredit)
                            <div>
                                <label class="text-xs font-black text-slate-700" for="payment_kind">Forma de cobro</label>
                                <select id="payment_kind" x-model="paymentKind" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
                                    <option value="cash">Contado</option>
                                    <option value="mixed">Pago parcial + crédito</option>
                                    <option value="credit">A crédito</option>
                                </select>
                            </div>
                        @else
                            <input type="hidden" x-model="paymentKind" value="cash">
                        @endif

                        <div x-show="paymentKind !== 'credit'" x-cloak>
                            <label class="text-xs font-black text-slate-700" for="payment_method">Método de pago</label>
                            <select id="payment_method" x-model="paymentMethod" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
                                @foreach ($paymentMethods as $method => $config)
                                    @continue($method === 'credit')
                                    <option value="{{ $method }}">{{ $config['label'] ?? $method }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="paymentKind === 'mixed'" x-cloak>
                            <label class="text-xs font-black text-slate-700" for="mixed_credit_amount">Monto pendiente a crédito</label>
                            <input id="mixed_credit_amount" x-model.number="mixedCreditAmount" type="number" min="0.01" step="0.01" :max="total" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        <div x-show="paymentKind !== 'cash'" x-cloak>
                            <label class="text-xs font-black text-slate-700" for="customer_id">Cliente</label>
                            <select id="customer_id" name="customer_id" x-model="customerId" :required="paymentKind !== 'cash'" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100">
                                <option value="">Selecciona un cliente</option>
                                <template x-for="customer in customers" :key="customer.id">
                                    <option :value="customer.id" x-text="customer.name + (customer.balance > 0 ? ' · debe ' + money(customer.balance) : '')"></option>
                                </template>
                            </select>
                            <p x-show="!customers.length" x-cloak class="mt-1 text-xs font-semibold text-amber-700">Crea primero un cliente desde Clientes y cobros.</p>
                        </div>

                        <div x-show="paymentKind !== 'cash'" x-cloak>
                            <label class="text-xs font-black text-slate-700" for="due_date">Vencimiento (opcional)</label>
                            <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        </div>

                        <div class="space-y-2 rounded-2xl bg-slate-50 p-4">
                            <div class="flex items-center justify-between text-sm text-slate-500"><span>Subtotal</span><span class="font-bold text-slate-800" x-text="money(total)"></span></div>
                            <div x-show="computedCreditAmount > 0" x-cloak class="flex items-center justify-between text-sm text-amber-700"><span>A crédito</span><span class="font-bold" x-text="money(computedCreditAmount)"></span></div>
                            <div class="flex items-end justify-between border-t border-slate-200 pt-3"><span class="text-base font-black text-slate-950">Total</span><span class="text-2xl font-black text-blue-950" x-text="money(total)"></span></div>
                            <p x-show="paidAmount > 0 && computedCreditAmount > 0" x-cloak class="text-right text-xs font-semibold text-emerald-600">Pago ahora: <span x-text="money(paidAmount)"></span></p>
                        </div>

                        <p x-show="formError" x-text="formError" class="text-xs font-bold text-rose-600"></p>
                        <button type="submit" :disabled="!cart.length || submitting" class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                            <span x-show="!submitting">Cobrar venta</span>
                            <span x-show="submitting" x-cloak>Registrando...</span>
                            <svg x-show="!submitting" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
                        </button>
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
                selectedCategory: 'all',
                saleMode: 'retail',
                paymentKind: 'cash',
                paymentMethod: 'cash',
                customerId: '',
                mixedCreditAmount: 0,
                submitting: false,
                formError: '',

                get categories() {
                    return [...new Set(this.products.map((product) => product.category).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
                },

                get filteredProducts() {
                    const query = this.search.trim().toLowerCase();
                    return this.products.filter((product) => {
                        if (this.saleMode === 'wholesale' && product.wholesale_price === null) return false;
                        if (this.selectedCategory !== 'all' && product.category !== this.selectedCategory) return false;
                        if (!query) return true;
                        return [product.name, product.code, product.category, product.sale_unit_label]
                            .filter(Boolean)
                            .some((value) => String(value).toLowerCase().includes(query));
                    });
                },

                get availableProductCount() {
                    return this.products.filter((product) => product.stock > 0).length;
                },

                get cartItemCount() {
                    return this.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                },

                get total() {
                    return this.cart.reduce((sum, item) => sum + this.lineTotal(item), 0);
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
                    if (product.stock <= 0) return 'bg-rose-50 text-rose-700';
                    if (product.stock <= 5) return 'bg-amber-50 text-amber-700';
                    return 'bg-emerald-50 text-emerald-700';
                },

                stockLabel(product) {
                    if (product.stock <= 0) return 'Agotado';
                    return `En stock (${product.stock})`;
                },

                canAdd(product) {
                    return product.stock > 0 && !(this.saleMode === 'wholesale' && product.wholesale_price === null);
                },

                addProduct(product) {
                    if (!this.canAdd(product)) return;
                    const existing = this.cart.find((item) => item.id === product.id);
                    if (existing) {
                        existing.quantity = Math.min(product.stock, existing.quantity + 1);
                        return;
                    }
                    this.cart.push({
                        id: product.id,
                        name: product.name,
                        imageUrl: product.image_url,
                        quantity: 1,
                        stock: product.stock,
                        unitPrice: this.priceFor(product),
                        saleUnit: product.sale_unit,
                        saleUnitLabel: product.sale_unit_label,
                        volumeMl: product.volume_ml,
                        sourceProductId: product.source_product_id,
                    });
                    this.formError = '';
                },

                changeQuantity(item, delta) {
                    item.quantity = Math.max(1, Math.min(item.stock, item.quantity + delta));
                },

                removeItem(id) {
                    this.cart = this.cart.filter((item) => item.id !== id);
                },

                clearCart() {
                    this.cart = [];
                    this.formError = '';
                },

                repriceCart() {
                    this.cart = this.cart.filter((item) => {
                        const product = this.products.find((candidate) => candidate.id === item.id);
                        if (!product || !this.canAdd(product)) return false;
                        item.unitPrice = this.priceFor(product);
                        item.stock = product.stock;
                        item.quantity = Math.min(item.quantity, product.stock);
                        return product.stock > 0;
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
                    this.submitting = true;
                },

                init() {
                    this.$watch('saleMode', () => this.repriceCart());
                    this.$watch('paymentKind', (kind) => {
                        if (kind === 'credit') this.mixedCreditAmount = this.total;
                        if (kind === 'cash') this.mixedCreditAmount = 0;
                    });
                    this.$watch('cart', () => { this.formError = ''; }, { deep: true });
                },
            };
        }
    </script>
</x-layouts.app>
