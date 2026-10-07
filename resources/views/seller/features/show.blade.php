<x-layouts.app :title="$feature['title'].' | '.$shop->name">
    <x-admin.header />

    <main class="min-h-screen bg-[#f7f7f6] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-[1600px] space-y-5">
            <x-seller.shop-header :shop="$shop" activeTab="feature" />

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-6 sm:px-8">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">{{ $feature['group'] }}</p>
                    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-black tracking-tight text-slate-950">{{ $feature['title'] }}</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $feature['description'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            {{ $module['kind'] === 'prepared' ? $feature['status'] : 'Operativo' }}
                        </span>
                    </div>
                </div>

                @if ($module['kind'] !== 'prepared')
                    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
                        <div class="grid gap-4 sm:grid-cols-3">
                            @foreach ($module['kpis'] as $kpi)
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $kpi['label'] }}</p>
                                    <p class="mt-2 text-2xl font-black tabular-nums {{ match ($kpi['tone']) { 'emerald' => 'text-emerald-700', 'amber' => 'text-amber-700', 'rose' => 'text-rose-700', 'blue' => 'text-blue-700', default => 'text-slate-900' } }}">{{ $kpi['value'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if (session('status'))
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif

                        @if ($featureKey === 'quotes')
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5" x-data="{
                                products: @js($module['quoteProducts'] ?? []),
                                search: '',
                                selectedCategory: 'all',
                                cart: [],
                                get categories() { return [...new Set(this.products.map(product => product.category).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')); },
                                get filteredProducts() {
                                    const query = this.search.trim().toLowerCase();
                                    return this.products.filter(product => {
                                        if (this.selectedCategory !== 'all' && product.category !== this.selectedCategory) return false;
                                        if (!query) return true;
                                        return [product.name, product.code, product.category, product.brand, product.sale_unit_label]
                                            .filter(Boolean).join(' ').toLowerCase().includes(query);
                                    });
                                },
                                add(product) {
                                    const line = this.cart.find(item => item.id === product.id);
                                    if (line) { line.quantity += 1; return; }
                                    this.cart.push({ id: product.id, name: product.name, price: Number(product.price) || 0, quantity: 1 });
                                },
                                remove(index) { this.cart.splice(index, 1); },
                                total() { return this.cart.reduce((total, line) => total + ((Number(line.price) || 0) * (Number(line.quantity) || 0)), 0); }
                            }">
                                <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-base font-black text-slate-900">Nueva cotización</h2><p class="mt-1 text-xs text-slate-500">Elige productos como en el punto de venta y arma una propuesta completa.</p></div><span class="rounded-full bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 shadow-sm" x-text="`${filteredProducts.length} producto(s)`"></span></div>
                                <form method="POST" action="{{ route('seller.shops.quotes.store', $shop) }}" @submit="if (!cart.length) $event.preventDefault()" class="space-y-4">
                                    @csrf
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <input name="customer_name" value="{{ old('customer_name') }}" placeholder="Nombre del cliente" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                        <input name="customer_phone" value="{{ old('customer_phone') }}" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    </div>
                                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_400px]">
                                        <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                                            <div class="relative">
                                                <input x-model="search" placeholder="Buscar producto, código, marca o unidad..." class="w-full rounded-xl border border-slate-300 py-3 pl-4 pr-4 text-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Filtrar cotizaciones por categoría">
                                                <button type="button" @click="selectedCategory = 'all'" :class="selectedCategory === 'all' ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-4 py-2 text-xs font-black">Todos</button>
                                                <template x-for="item in categories" :key="item">
                                                    <button type="button" @click="selectedCategory = item" :class="selectedCategory === item ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700'" class="shrink-0 rounded-full px-4 py-2 text-xs font-black" x-text="item"></button>
                                                </template>
                                            </div>
                                            <div class="mt-4 grid max-h-[620px] gap-4 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                                                <template x-for="product in filteredProducts" :key="product.id">
                                                    <article class="group relative flex min-h-[245px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                                                        <div class="relative flex h-32 items-center justify-center overflow-hidden bg-slate-50">
                                                            <template x-if="product.image_url"><img :src="product.image_url" :alt="product.name" class="h-full w-full object-contain p-3 transition duration-300 group-hover:scale-105"></template>
                                                            <template x-if="!product.image_url"><div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-2xl font-black text-blue-700" x-text="product.name.charAt(0).toUpperCase()"></div></template>
                                                            <button type="button" @click="add(product)" class="absolute right-2 top-2 flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-xl font-black leading-none text-white shadow-sm transition hover:bg-blue-700" :aria-label="`Agregar ${product.name}`">+</button>
                                                            <span x-show="product.stock !== null && product.stock > 0 && product.stock <= 5" class="absolute bottom-2 left-2 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black text-amber-800">Pocas unidades</span>
                                                        </div>
                                                        <div class="flex flex-1 flex-col p-3">
                                                            <p class="truncate text-[10px] font-black uppercase tracking-wide text-blue-600" x-text="product.category || 'Sin categoría'"></p>
                                                            <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-black text-slate-900" x-text="product.name"></h3>
                                                            <p class="mt-2 text-base font-black text-slate-950" x-text="`RD$ ${Number(product.price).toLocaleString('es-DO', {minimumFractionDigits: 2})}`"></p>
                                                            <div class="mt-auto flex items-center justify-between gap-2 pt-3 text-[10px] font-bold">
                                                                <span :class="product.stock !== null && product.stock <= 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="product.stock === null ? 'Sin control de stock' : product.stock <= 0 ? 'Agotado' : `${product.stock} disponible(s)`"></span>
                                                                <span class="truncate text-slate-400" x-text="product.code || product.sale_unit_label || ''"></span>
                                                            </div>
                                                        </div>
                                                    </article>
                                                </template>
                                                <p x-show="filteredProducts.length === 0" class="sm:col-span-2 xl:col-span-3 rounded-xl border border-dashed border-slate-300 px-3 py-10 text-center text-xs text-slate-500">No encontramos productos con esos filtros.</p>
                                            </div>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-white p-3 xl:sticky xl:top-4 xl:self-start">
                                            <div class="flex items-center justify-between"><h3 class="text-sm font-black text-slate-900">Carrito de cotización</h3><span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700" x-text="`${cart.length} producto(s)`"></span></div>
                                            <div class="mt-3 space-y-2">
                                                <template x-for="(line, index) in cart" :key="line.id">
                                                    <div class="rounded-xl border border-slate-200 p-3">
                                                        <div class="flex items-start justify-between gap-2"><p class="text-xs font-black text-slate-800" x-text="line.name"></p><button type="button" @click="remove(index)" class="text-xs font-bold text-rose-600">Quitar</button></div>
                                                        <div class="mt-2 grid grid-cols-2 gap-2"><label class="text-[11px] text-slate-500">Cantidad<input type="number" min="1" x-model.number="line.quantity" :name="`items[${index}][quantity]`" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></label><label class="text-[11px] text-slate-500">Precio unitario<input type="number" min="0" step="0.01" x-model="line.price" :name="`items[${index}][unit_price]`" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></label></div>
                                                        <input type="hidden" :name="`items[${index}][product_id]`" :value="line.id">
                                                    </div>
                                                </template>
                                                <p x-show="cart.length === 0" class="rounded-xl border border-dashed border-slate-300 px-3 py-8 text-center text-xs text-slate-500">Agrega productos desde la búsqueda.</p>
                                            </div>
                                            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-sm"><span class="font-bold text-slate-500">Total</span><strong class="text-lg text-slate-900" x-text="`RD$ ${total().toLocaleString('es-DO', {minimumFractionDigits: 2})}`"></strong></div>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-[1fr_auto]"><textarea name="notes" placeholder="Notas (opcional)" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></textarea><button :disabled="cart.length === 0" :class="cart.length === 0 ? 'cursor-not-allowed bg-slate-300' : 'bg-blue-600 hover:bg-blue-700'" class="rounded-xl px-4 py-2.5 text-sm font-black text-white">Guardar cotización</button></div>
                                </form>
                            </div>
                        @endif

                        @if ($featureKey === 'sales')
                            @php($salesFilters = $module['filters'] ?? ['period' => 'today', 'status' => 'all', 'search' => '', 'count' => 0])
                            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5" aria-labelledby="sales-filters-title">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 id="sales-filters-title" class="text-base font-black text-slate-900">Historial de ventas</h2>
                                        <p class="mt-1 text-xs text-slate-500">Busca por factura, cliente o vendedor y revisa el período que necesitas.</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1.5 text-[11px] font-black text-slate-600 shadow-sm">{{ number_format((int) $salesFilters['count']) }} resultado(s)</span>
                                </div>
                                <form method="GET" action="{{ route('seller.shops.feature', [$shop, 'feature' => 'sales']) }}" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_auto]">
                                    <label class="sr-only" for="sales-search">Buscar ventas</label>
                                    <input id="sales-search" name="q" value="{{ $salesFilters['search'] }}" placeholder="Buscar factura, cliente o vendedor..." class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <label class="sr-only" for="sales-status">Estado de la venta</label>
                                    <select id="sales-status" name="status" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="all" @selected($salesFilters['status'] === 'all')>Todos los estados</option>
                                        <option value="paid" @selected($salesFilters['status'] === 'paid')>Pagadas</option>
                                        <option value="credit" @selected($salesFilters['status'] === 'credit')>A crédito</option>
                                        <option value="partial" @selected($salesFilters['status'] === 'partial')>Abono parcial</option>
                                        <option value="void" @selected($salesFilters['status'] === 'void')>Anuladas</option>
                                    </select>
                                    <button class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Aplicar filtros</button>
                                </form>
                                <div class="mt-3 flex flex-wrap gap-2" role="tablist" aria-label="Período de ventas">
                                    @foreach (['today' => 'Hoy', 'month' => 'Este mes', 'last_7' => 'Últimos 7 días', 'all' => 'Todo'] as $periodKey => $periodLabel)
                                        <a href="{{ request()->fullUrlWithQuery(['period' => $periodKey]) }}" class="rounded-full px-3.5 py-2 text-xs font-black transition {{ $salesFilters['period'] === $periodKey ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:text-blue-700' }}">{{ $periodLabel }}</a>
                                    @endforeach
                                    @if ($salesFilters['search'] !== '' || $salesFilters['status'] !== 'all' || $salesFilters['period'] !== 'today')
                                        <a href="{{ route('seller.shops.feature', [$shop, 'feature' => 'sales']) }}" class="rounded-full border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-500 hover:text-blue-700">Limpiar</a>
                                    @endif
                                </div>
                            </section>
                        @endif

                        @if ($featureKey === 'photos')
                            <section class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5" x-data="{
                                products: @js($module['photoProducts'] ?? []),
                                filter: 'pending',
                                query: '',
                                active: null,
                                results: [],
                                loading: false,
                                error: '',
                                get filtered() {
                                    const query = this.query.trim().toLowerCase();
                                    return this.products.filter(product => {
                                        if (this.filter === 'pending' && product.image_count > 0) return false;
                                        if (this.filter === 'complete' && product.image_count === 0) return false;
                                        return !query || `${product.name} ${product.code}`.toLowerCase().includes(query);
                                    });
                                },
                                async search(product) {
                                    this.active = product.id;
                                    this.results = [];
                                    this.error = '';
                                    this.loading = true;
                                    try {
                                        const response = await fetch(`{{ route('seller.shops.products.images.search', $shop) }}?q=${encodeURIComponent(product.name)}`, { headers: { Accept: 'application/json' } });
                                        const payload = await response.json();
                                        if (!response.ok) throw new Error(payload.message || 'No se pudieron buscar sugerencias.');
                                        this.results = payload.results || [];
                                        if (!this.results.length) this.error = 'No encontramos sugerencias para este producto.';
                                    } catch (searchError) {
                                        this.error = searchError.message;
                                    } finally {
                                        this.loading = false;
                                    }
                                }
                            }">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 class="text-base font-black text-slate-900">Gestor de fotografías</h2>
                                        <p class="mt-1 text-xs text-slate-500">Revisa pendientes, busca sugerencias y confirma cada imagen antes de aplicarla.</p>
                                    </div>
                                    <a href="{{ route('seller.shops.products.index', $shop) }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-700 hover:border-blue-200 hover:text-blue-700">Abrir productos</a>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <label class="sr-only" for="photo-search">Buscar producto sin foto</label>
                                    <input id="photo-search" x-model="query" type="search" placeholder="Buscar producto o código..." class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <div class="flex gap-2 overflow-x-auto" role="tablist" aria-label="Filtrar fotografías">
                                        <button type="button" @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-600'" class="shrink-0 rounded-xl px-3.5 py-2.5 text-xs font-black">Pendientes</button>
                                        <button type="button" @click="filter = 'complete'" :class="filter === 'complete' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-600'" class="shrink-0 rounded-xl px-3.5 py-2.5 text-xs font-black">Con foto</button>
                                        <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-600'" class="shrink-0 rounded-xl px-3.5 py-2.5 text-xs font-black">Todos</button>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    <template x-for="product in filtered" :key="product.id">
                                        <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                                            <div class="flex items-center gap-3">
                                                <template x-if="product.image_url"><img :src="product.image_url" :alt="product.name" class="h-16 w-16 rounded-xl bg-slate-50 object-contain"></template>
                                                <template x-if="!product.image_url"><div class="flex h-16 w-16 items-center justify-center rounded-xl bg-slate-100 text-xl font-black text-slate-400" x-text="product.name.charAt(0).toUpperCase()"></div></template>
                                                <div class="min-w-0 flex-1"><h3 class="truncate text-sm font-black text-slate-900" x-text="product.name"></h3><p class="mt-1 text-[11px] text-slate-500" x-text="product.code"></p><span :class="product.image_count ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'" class="mt-2 inline-flex rounded-full px-2 py-1 text-[10px] font-black" x-text="product.image_count ? `${product.image_count} foto(s)` : 'Sin foto'"></span></div>
                                            </div>
                                            <div class="mt-3 flex gap-2"><button type="button" @click="search(product)" class="flex-1 rounded-xl bg-blue-600 px-3 py-2 text-[11px] font-black text-white hover:bg-blue-700">Buscar sugerencias</button><a :href="product.edit_url" class="rounded-xl border border-slate-200 px-3 py-2 text-[11px] font-black text-slate-700 hover:border-blue-200 hover:text-blue-700">Editar</a></div>
                                            <div x-show="active === product.id" x-cloak class="mt-3 border-t border-slate-100 pt-3">
                                                <p x-show="loading" class="text-xs font-bold text-blue-700">Buscando imágenes…</p>
                                                <p x-show="error" x-text="error" class="text-xs font-bold text-rose-600"></p>
                                                <div class="grid grid-cols-3 gap-2" x-show="!loading && results.length">
                                                    <template x-for="result in results" :key="result.url">
                                                        <form method="POST" :action="product.image_store_url" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                            @csrf
                                                            <input type="hidden" name="url" :value="result.url">
                                                            <img :src="result.thumbnail || result.url" :alt="result.title || product.name" class="h-20 w-full object-cover">
                                                            <button class="w-full px-2 py-2 text-[10px] font-black text-blue-700 hover:bg-blue-50">Usar esta foto</button>
                                                        </form>
                                                    </template>
                                                </div>
                                            </div>
                                        </article>
                                    </template>
                                    <p x-show="filtered.length === 0" class="sm:col-span-2 xl:col-span-3 rounded-xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-xs text-slate-500">No hay productos en este filtro.</p>
                                </div>
                            </section>
                        @endif

                        @if (in_array($featureKey, ['containers', 'loads', 'purchase_invoices'], true))
                            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 sm:p-5">
                                <div class="mb-4"><h2 class="text-base font-black text-slate-900">Registrar recepción de compra</h2><p class="mt-1 text-xs text-slate-500">La recepción crea un lote separado y conserva el costo exacto de esta compra.</p></div>
                                <form method="POST" action="{{ route('seller.shops.purchases.store', $shop) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $featureKey === 'containers' ? 'container' : ($featureKey === 'loads' ? 'load' : 'purchase_invoice') }}">
                                    <input required name="document_number" placeholder="No. documento" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <select name="supplier_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Suplidor (opcional)</option>@foreach (($module['suppliers'] ?? []) as $supplier)<option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>@endforeach</select>
                                    <select required name="product_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Producto</option>@foreach (($module['purchaseProducts'] ?? []) as $product)<option value="{{ $product['id'] }}">{{ $product['name'] }}</option>@endforeach</select>
                                    <input required name="quantity" type="number" min="1" placeholder="Cantidad" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <input required name="unit_cost" type="number" min="0" step="0.01" placeholder="Costo unitario" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <button class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-black text-white hover:bg-amber-700">Recibir inventario</button>
                                </form>
                            </div>
                        @elseif ($featureKey === 'suppliers')
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5"><div class="mb-4"><h2 class="text-base font-black text-slate-900">Nuevo suplidor</h2><p class="mt-1 text-xs text-slate-500">Guarda sus datos para asociarlos a futuras recepciones.</p></div><form method="POST" action="{{ route('seller.shops.suppliers.store', $shop) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf<input required name="name" placeholder="Nombre del suplidor" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="phone" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="email" type="email" placeholder="Correo" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Guardar suplidor</button></form></div>
                        @endif

                        @if ($featureKey === 'partners')
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5"><h2 class="mb-4 text-base font-black text-slate-900">Nuevo socio</h2><form method="POST" action="{{ route('seller.shops.partners.store', $shop) }}" class="grid gap-3 sm:grid-cols-2">@csrf<input required name="name" placeholder="Nombre" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="ownership_percent" type="number" min="0" max="100" step="0.01" placeholder="Participación %" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="email" type="email" placeholder="Correo" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="phone" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><button class="sm:col-span-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Guardar socio</button></form></div>
                                <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 sm:p-5"><h2 class="mb-4 text-base font-black text-slate-900">Aporte o retiro</h2><form method="POST" action="{{ route('seller.shops.partners.transactions.store', [$shop, 'partner' => '__partner__']) }}" onsubmit="this.action=this.action.replace('__partner__',this.partner_id.value)" class="grid gap-3 sm:grid-cols-2">@csrf<select required name="partner_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Socio</option>@foreach (($module['partners'] ?? []) as $partner)<option value="{{ $partner['id'] }}">{{ $partner['name'] }}</option>@endforeach</select><select required name="type" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="contribution">Aporte</option><option value="withdrawal">Retiro</option><option value="distribution">Distribución</option></select><input required name="amount" type="number" min="0.01" step="0.01" placeholder="Monto" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="notes" placeholder="Nota" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><button class="sm:col-span-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-black text-white hover:bg-amber-700">Registrar en caja</button></form></div>
                            </div>
                        @endif

                        @if ($featureKey === 'day_close')
                            <div class="rounded-2xl border {{ $module['session'] ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50' }} p-4 sm:p-5">
                                @if ($module['session'])
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700">Sesión de caja activa</p>
                                            <p class="mt-1 text-sm text-slate-600">Efectivo esperado: <strong class="text-lg text-emerald-800">RD$ {{ $module['session']['expected'] }}</strong></p>
                                        </div>
                                        <form method="POST" action="{{ route('seller.shops.cash.close', [$shop, $module['session']['id']]) }}" class="grid w-full gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-end">
                                            @csrf
                                            <div><label class="block text-[11px] font-bold text-slate-600" for="day-close-counted">Efectivo contado</label><input id="day-close-counted" name="counted_amount" required min="0" step="0.01" type="number" placeholder="0.00" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-black tabular-nums sm:w-40"></div>
                                            <button class="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 sm:w-auto">Cerrar caja</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-black text-slate-800">No hay una sesión de caja abierta</p><p class="mt-1 text-xs text-slate-500">Abre la caja para que los cobros en efectivo queden conciliados.</p></div><a wire:navigate.hover href="{{ route('seller.shops.cash.index', $shop) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700">Abrir caja</a></div>
                                @endif
                            </div>
                        @endif

                        @if ($featureKey === 'day_close' && ! empty($module['day_close']))
                            <div class="grid gap-4 lg:grid-cols-2">
                                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Ventas cobradas</p>
                                            <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $module['day_close']['sales_total'] }}</p>
                                        </div>
                                        <span class="rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-black text-blue-700">{{ $module['day_close']['sales_count'] }} cobro(s)</span>
                                    </div>
                                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                                        @forelse ($module['day_close']['payments'] as $payment)
                                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-slate-600">{{ $payment['label'] }}</span><span class="font-black tabular-nums text-slate-900">{{ $payment['value'] }}</span></div>
                                        @empty
                                            <p class="text-sm text-slate-500">No se recibieron cobros de ventas.</p>
                                        @endforelse
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Abonos recibidos</p>
                                    <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $module['day_close']['collections_total'] }}</p>
                                    <p class="mt-4 text-sm text-slate-500">{{ $module['day_close']['collections_count'] ? $module['day_close']['collections_count'].' abono(s) aplicado(s) a cuentas por cobrar.' : 'No se recibieron abonos.' }}</p>
                                </section>

                                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Gastos</p>
                                    <p class="mt-2 text-3xl font-black tabular-nums text-rose-700">{{ $module['day_close']['expenses_total'] }}</p>
                                    <p class="mt-4 text-sm text-slate-500">Egresos registrados con fecha de hoy.</p>
                                </section>

                                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Devoluciones</p>
                                    <p class="mt-2 text-3xl font-black tabular-nums text-amber-700">{{ $module['day_close']['refunds_total'] }}</p>
                                    <p class="mt-4 text-sm text-slate-500">Reembolsos registrados durante el día.</p>
                                </section>
                            </div>

                            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Efectivo en caja</p>
                                        <h2 class="mt-1 text-xl font-black text-slate-950">Concilia el efectivo antes de cerrar</h2>
                                    </div>
                                    <span class="rounded-full {{ $module['day_close']['cash'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} px-3 py-1.5 text-xs font-black">{{ $module['day_close']['cash'] ? 'Sesión activa' : 'Sin sesión abierta' }}</span>
                                </div>
                                @if ($module['day_close']['cash'])
                                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-xl bg-emerald-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Entró</p><p class="mt-1 text-xl font-black tabular-nums text-emerald-900">{{ $module['day_close']['cash']['in'] }}</p></div>
                                        <div class="rounded-xl bg-rose-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-rose-700">Salió</p><p class="mt-1 text-xl font-black tabular-nums text-rose-900">{{ $module['day_close']['cash']['out'] }}</p></div>
                                        <div class="rounded-xl bg-blue-50 p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Deberías tener</p><p class="mt-1 text-xl font-black tabular-nums text-blue-900">{{ $module['day_close']['cash']['expected'] }}</p></div>
                                    </div>
                                    <p class="mt-4 text-xs leading-5 text-slate-500">Solo el efectivo se cuenta aquí. Transferencias y tarjetas se revisan contra el banco, no contra la gaveta.</p>
                                @else
                                    <p class="mt-4 text-sm text-slate-500">Abre una sesión desde Control de caja para registrar el arqueo y conciliar los cobros en efectivo.</p>
                                @endif
                            </section>
                        @endif

                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div>
                                    <h2 class="text-base font-black text-slate-900">Actividad reciente</h2>
                                    <p class="mt-1 text-xs text-slate-500">Datos tomados de los módulos contables y de inventario existentes.</p>
                                </div>
                                @if (! empty($module['actions']))
                                    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                                        @foreach ($module['actions'] as $action)
                                            <a wire:navigate.hover href="{{ $action['url'] }}" class="flex-1 rounded-xl px-3.5 py-2 text-center text-xs font-black sm:flex-none {{ ($action['tone'] ?? 'secondary') === 'primary' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-200 bg-white text-slate-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700' }}">{{ $action['label'] }}</a>
                                        @endforeach
                                    </div>
                                @elseif ($featureKey === 'sales')
                                    <a wire:navigate.hover href="{{ route('seller.shops.pos', $shop) }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Nueva venta</a>
                                @elseif (in_array($featureKey, ['orders', 'encargos', 'shipments'], true))
                                    <a wire:navigate.hover href="{{ route('seller.shops.pos', $shop) }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Ir a Terminal</a>
                                @elseif ($featureKey === 'credit')
                                    <a wire:navigate.hover href="{{ route('seller.shops.customers.index', $shop) }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Gestionar clientes</a>
                                @endif
                            </div>

                            @if (count($module['rows']))
                                <div class="divide-y divide-slate-100">
                                    @foreach ($module['rows'] as $row)
                                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-black text-slate-900">{{ $row['primary'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $row['secondary'] }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                                                <span class="text-sm font-black tabular-nums text-slate-900">{{ $row['value'] }}</span>
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $row['status'] }}</span>
                                                @if (($row['can_confirm'] ?? false) && in_array($featureKey, ['orders', 'encargos', 'shipments'], true))
                                                    <form method="POST" action="{{ route('seller.shops.orders.confirm', [$shop, $row['id']]) }}">
                                                        @csrf
                                                        <button class="rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-black text-white hover:bg-emerald-700">Confirmar venta</button>
                                                    </form>
                                                @endif
                                                @if (($row['can_convert'] ?? false) && $featureKey === 'quotes')
                                                    <form method="POST" action="{{ route('seller.shops.quotes.convert', [$shop, 'quote' => $row['id']]) }}">
                                                        @csrf
                                                        <button class="rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-black text-white hover:bg-emerald-700">Convertir en venta</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="px-5 py-12 text-center text-sm text-slate-500">No hay registros para mostrar todavía.</div>
                            @endif
                        </div>

                        @if ($module['note'])
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/60 px-5 py-4 text-sm leading-6 text-blue-900">{{ $module['note'] }}</div>
                        @endif

                        @if (! empty($related))
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($related as $item)
                                    <a wire:navigate.hover href="{{ $item['url'] }}" class="group flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                        <span>{{ $item['label'] }}</span>
                                        <span class="text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-600">→</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                <div class="grid gap-5 p-6 sm:p-8 lg:grid-cols-[1.15fr_.85fr]">
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 sm:p-8">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-xl font-black text-blue-700">{{ strtoupper(substr($feature['title'], 0, 1)) }}</div>
                        <h2 class="mt-5 text-lg font-black text-slate-900">Estamos preparando este espacio</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">El menú ya está organizado y protegido por la misma cuenta y tienda. Aquí agregaremos el flujo completo, manteniendo los datos y las reglas contables de MiCatalogo.</p>
                        <div class="mt-6 flex items-center gap-3 text-xs font-bold text-slate-500">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">✓</span>
                            Acceso preparado
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-blue-700">2</span>
                            Contenido en desarrollo
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Puedes continuar con</p>
                        <div class="mt-4 space-y-2">
                            @foreach ($related as $item)
                                <a wire:navigate.hover href="{{ $item['url'] }}" class="group flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                    <span>{{ $item['label'] }}</span>
                                    <span class="text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-600">→</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
