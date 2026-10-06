@php
    $posPresentation = $presentation['pos'] ?? [];
    $showWholesale = ($posPresentation['show_wholesale'] ?? false) === true;
    $showCredit = ($posPresentation['show_credit'] ?? false) === true;
@endphp

<x-layouts.app :title="'Punto de venta | '.$shop->name">
    <x-admin.header />

    <main
        class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"
        x-data="webPos(@js($products), @js($customers), @js($clientSaleUuid), @js($posPresentation))"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="pos" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-bold">No se pudo registrar la venta.</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Operación</p>
                    <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950">Punto de venta</h1>
                    <p class="mt-1 text-sm text-slate-500">Registra ventas al contado, por mayor o a crédito sin salir de tu tienda.</p>
                </div>
                <a href="{{ route('seller.shops.inventory.index', $shop) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Ver inventario
                </a>
            </div>

            <form method="POST" action="{{ route('seller.shops.pos.store', $shop) }}" @submit="if (!cart.length) { $event.preventDefault(); formError = 'Agrega al menos un producto.'; }" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]">
                @csrf
                <input type="hidden" name="client_sale_uuid" value="{{ $clientSaleUuid }}">
                <input type="hidden" name="payment_status" value="paid">
                <input type="hidden" name="sale_mode" :value="saleMode">
                <input type="hidden" name="credit_amount" :value="computedCreditAmount">
                <input type="hidden" name="payments[0][method]" :value="paymentMethod">
                <input type="hidden" name="payments[0][amount]" :value="paidAmount.toFixed(2)">

                <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Selecciona productos</h2>
                            <p class="mt-1 text-xs text-slate-500"><span x-text="filteredProducts.length"></span> disponibles para vender</p>
                        </div>
                        <div class="relative w-full sm:max-w-xs">
                            <input x-model="search" type="search" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 pl-10 text-sm outline-none transition focus:border-blue-600 focus:bg-white focus:ring-2 focus:ring-blue-100" placeholder="Buscar producto, código...">
                            <svg class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.3-4.3m1.8-5.2a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="product in filteredProducts" :key="product.id">
                            <article class="flex min-h-[145px] flex-col rounded-2xl border border-slate-200 bg-slate-50 p-3 transition hover:border-blue-300 hover:bg-blue-50/40">
                                <div class="flex gap-3">
                                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                        <template x-if="product.image_url"><img :src="product.image_url" :alt="product.name" class="h-full w-full object-cover"></template>
                                        <template x-if="!product.image_url"><div class="flex h-full items-center justify-center text-xl font-black text-slate-300" x-text="product.name.charAt(0).toUpperCase()"></div></template>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-sm font-bold text-slate-900" x-text="product.name"></h3>
                                        <p class="mt-0.5 truncate text-[11px] text-slate-500" x-text="product.code || product.sale_unit_label"></p>
                                        <p class="mt-1 text-sm font-black text-blue-700" x-text="money(priceFor(product))"></p>
                                        <p class="text-[11px] font-semibold" :class="product.stock > 0 ? 'text-emerald-700' : 'text-rose-600'" x-text="product.stock > 0 ? `Stock: ${product.stock} ${product.sale_unit_label}` : 'Agotado'"></p>
                                    </div>
                                </div>
                                <button type="button" @click="addProduct(product)" :disabled="product.stock <= 0 || (saleMode === 'wholesale' && product.wholesale_price === null)" class="mt-3 w-full rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                                    <span x-text="saleMode === 'wholesale' && product.wholesale_price === null ? 'Sin precio por mayor' : 'Agregar a la venta'"></span>
                                </button>
                            </article>
                        </template>
                    </div>

                    <div x-show="!filteredProducts.length" x-cloak class="mt-6 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                        No hay productos que coincidan con la búsqueda o el tipo de venta.
                    </div>
                </section>

                <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Venta actual</h2>
                            <p class="mt-1 text-xs text-slate-500"><span x-text="cart.length"></span> producto(s)</p>
                        </div>
                        <button type="button" @click="clearCart()" x-show="cart.length" x-cloak class="text-xs font-bold text-rose-600 hover:text-rose-800">Vaciar</button>
                    </div>

                    <div class="mt-4 max-h-72 space-y-3 overflow-y-auto pr-1">
                        <template x-for="(item, index) in cart" :key="item.id">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900" x-text="item.name"></p>
                                        <p class="mt-0.5 text-[11px] text-slate-500" x-text="money(item.unitPrice) + ' · ' + item.saleUnitLabel"></p>
                                    </div>
                                    <button type="button" @click="removeItem(item.id)" class="text-slate-400 hover:text-rose-600" aria-label="Quitar producto">✕</button>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center rounded-lg border border-slate-300 bg-white">
                                        <button type="button" @click="changeQuantity(item, -1)" class="px-2.5 py-1.5 text-lg font-bold text-slate-600">−</button>
                                        <span class="min-w-8 text-center text-sm font-black" x-text="item.quantity"></span>
                                        <button type="button" @click="changeQuantity(item, 1)" class="px-2.5 py-1.5 text-lg font-bold text-slate-600">+</button>
                                    </div>
                                    <span class="text-sm font-black text-slate-900" x-text="money(lineTotal(item))"></span>
                                </div>
                                <template x-for="(field, fieldIndex) in hiddenFields(item, index)" :key="field.name + fieldIndex">
                                    <input type="hidden" :name="field.name" :value="field.value">
                                </template>
                            </div>
                        </template>
                    </div>

                    <div x-show="!cart.length" x-cloak class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                        Agrega productos para comenzar la venta.
                    </div>

                    <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                        <div>
                            <label class="text-xs font-bold text-slate-700" for="sale_mode">Tipo de precio</label>
                            <select id="sale_mode" x-model="saleMode" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100">
                                <option value="retail">Venta al contado / detalle</option>
                                @if ($showWholesale)
                                    <option value="wholesale">Venta por mayor</option>
                                @endif
                            </select>
                        </div>

                        @if ($showCredit)
                            <div>
                                <label class="text-xs font-bold text-slate-700" for="payment_kind">Forma de cobro</label>
                                <select id="payment_kind" x-model="paymentKind" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100">
                                    <option value="cash">Contado</option>
                                    <option value="mixed">Pago parcial + crédito</option>
                                    <option value="credit">A crédito</option>
                                </select>
                            </div>
                        @else
                            <input type="hidden" x-model="paymentKind" value="cash">
                        @endif

                        <div x-show="paymentKind !== 'credit'" x-cloak>
                            <label class="text-xs font-bold text-slate-700" for="payment_method">Método de pago</label>
                            <select id="payment_method" x-model="paymentMethod" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100">
                                @foreach ($paymentMethods as $method => $config)
                                    @continue($method === 'credit')
                                    <option value="{{ $method }}">{{ $config['label'] ?? $method }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="paymentKind === 'mixed'" x-cloak>
                            <label class="text-xs font-bold text-slate-700" for="mixed_credit_amount">Monto pendiente a crédito</label>
                            <input id="mixed_credit_amount" x-model.number="mixedCreditAmount" type="number" min="0.01" step="0.01" :max="total" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100">
                        </div>

                        <div x-show="paymentKind !== 'cash'" x-cloak>
                            <label class="text-xs font-bold text-slate-700" for="customer_id">Cliente para la cuenta por cobrar</label>
                            <select id="customer_id" name="customer_id" x-model="customerId" :required="paymentKind !== 'cash'" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100">
                                <option value="">Selecciona un cliente</option>
                                <template x-for="customer in customers" :key="customer.id">
                                    <option :value="customer.id" x-text="customer.name + (customer.balance > 0 ? ' · debe ' + money(customer.balance) : '')"></option>
                                </template>
                            </select>
                            <p x-show="selectedCustomer && computedCreditAmount > Number(selectedCustomer.credit_limit)" x-cloak class="mt-1 text-xs font-bold text-rose-600">El crédito supera el límite disponible del cliente.</p>
                            <p x-show="!customers.length" x-cloak class="mt-1 text-xs text-amber-700">Crea primero un cliente desde Clientes y cobros.</p>
                        </div>

                        <div x-show="paymentKind !== 'cash'" x-cloak>
                            <label class="text-xs font-bold text-slate-700" for="due_date">Fecha de vencimiento (opcional)</label>
                            <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-100">
                        </div>

                        <div class="rounded-xl bg-slate-950 p-4 text-white">
                            <div class="flex items-center justify-between text-sm text-slate-300"><span>Subtotal</span><span x-text="money(total)"></span></div>
                            <div x-show="computedCreditAmount > 0" x-cloak class="mt-1 flex items-center justify-between text-sm text-amber-300"><span>A crédito</span><span x-text="money(computedCreditAmount)"></span></div>
                            <div class="mt-3 flex items-end justify-between border-t border-white/10 pt-3"><span class="text-sm font-bold">Total</span><span class="text-2xl font-black" x-text="money(total)"></span></div>
                            <p x-show="paidAmount > 0 && computedCreditAmount > 0" x-cloak class="mt-1 text-right text-xs text-emerald-300">Pago ahora: <span x-text="money(paidAmount)"></span></p>
                        </div>

                        <p x-show="formError" x-text="formError" class="text-xs font-bold text-rose-600"></p>
                        <button type="submit" :disabled="!cart.length || submitting" class="w-full rounded-xl bg-blue-700 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                            <span x-show="!submitting">Registrar venta</span>
                            <span x-show="submitting" x-cloak>Registrando...</span>
                        </button>
                    </div>
                </aside>
            </form>
        </div>
    </main>

    <script>
        function webPos(products, customers, clientSaleUuid, presentation) {
            return {
                products,
                customers,
                clientSaleUuid,
                presentation,
                cart: [],
                search: '',
                saleMode: 'retail',
                paymentKind: presentation.show_credit ? 'cash' : 'cash',
                paymentMethod: 'cash',
                customerId: '',
                mixedCreditAmount: 0,
                submitting: false,
                formError: '',

                get filteredProducts() {
                    const query = this.search.trim().toLowerCase();
                    return this.products.filter((product) => {
                        if (this.saleMode === 'wholesale' && product.wholesale_price === null) return false;
                        if (!query) return true;
                        return [product.name, product.code, product.sale_unit_label].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
                    });
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

                get selectedCustomer() {
                    return this.customers.find((customer) => customer.id === this.customerId) || null;
                },

                priceFor(product) {
                    return this.saleMode === 'wholesale' ? product.wholesale_price : product.price;
                },

                addProduct(product) {
                    if (product.stock <= 0 || (this.saleMode === 'wholesale' && product.wholesale_price === null)) return;
                    const existing = this.cart.find((item) => item.id === product.id);
                    if (existing) {
                        existing.quantity = Math.min(product.stock, existing.quantity + 1);
                        return;
                    }
                    this.cart.push({
                        id: product.id,
                        name: product.name,
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
                        if (!product || (this.saleMode === 'wholesale' && product.wholesale_price === null)) return false;
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
