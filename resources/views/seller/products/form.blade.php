@php
    $presentation = app(\App\Services\BusinessPresentationService::class)->resolve($shop);
    $terminology = $presentation['terminology'] ?? [];
    $productLabel = $terminology['product'] ?? 'Producto';
    $newProductLabel = $terminology['new_product'] ?? 'Nuevo producto';
    $capabilities = app(\App\Services\BusinessProfileService::class)->capabilities($shop);
    $productFields = app(\App\Services\BusinessProfileService::class)->profile($shop)['product_fields'] ?? [];
    $showInventory = ($capabilities['inventory'] ?? 'disabled') === 'enabled';
    $showWholesale = ($capabilities['wholesale'] ?? 'disabled') === 'enabled';
    $showSku = in_array('sku', $productFields, true);
    $showBarcode = in_array('barcode', $productFields, true);
    $showBrand = in_array('brand', $productFields, true);
    $showPerfumePresentation = ($capabilities['perfume_fields'] ?? 'disabled') === 'enabled' || ($capabilities['decants'] ?? 'disabled') === 'enabled';
@endphp
<x-layouts.app :title="($product->exists ? 'Editar ' . $productLabel : $newProductLabel) . ' | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => $product->exists ? 'Editar ' . $product->name : $newProductLabel]
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl">

            <section class="mt-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="border-b border-slate-200 pb-4">
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ $product->exists ? 'Editar ' . $productLabel : $newProductLabel }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $product->exists ? 'Actualiza los datos y disponibilidad de tu producto.' : 'Ingresa la información básica para publicarlo en tu catálogo.' }}
                    </p>
                </header>

                @if ($errors->any())
                    <div class="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        <p class="font-semibold">Revisa los siguientes errores:</p>
                        <ul class="mt-2 list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form class="mt-6 space-y-6" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('seller.shops.products.update', [$shop, $product]) : route('seller.shops.products.store', $shop) }}" x-data="{ trackInventory: {{ $showInventory && old('track_inventory', $product->exists ? ($product->inventory?->track_inventory ?? false) : true) ? 'true' : 'false' }}, saleUnit: '{{ old('sale_unit', $product->sale_unit ?? 'unit') }}', imagePreview: null, imageSearch: { query: '', results: [], selected: null, loading: false, error: '', endpoint: '{{ route('seller.shops.products.images.search', $shop) }}' }, async searchImages() { const query = (this.imageSearch.query || document.getElementById('name')?.value || '').trim(); if (query.length < 2) { this.imageSearch.error = 'Escribe primero el nombre del producto.'; return; } this.imageSearch.query = query; this.imageSearch.loading = true; this.imageSearch.error = ''; try { const response = await fetch(`${this.imageSearch.endpoint}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'No se pudieron consultar imágenes.'); this.imageSearch.results = payload.results || []; if (!this.imageSearch.results.length) this.imageSearch.error = 'No encontramos imágenes para ese nombre. Prueba con otra búsqueda.'; } catch (error) { this.imageSearch.error = error.message; } finally { this.imageSearch.loading = false; } } }">
                    @csrf
                    @if ($product->exists)
                        @method('PUT')
                    @endif

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-800" for="name">Nombre del {{ strtolower($productLabel) }} *</label>
                        <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="name" name="name" required type="text" value="{{ old('name', $product->name) }}" placeholder="Ej: Zapatillas Urbanas Pro">
                    </div>

                    @if ($showSku)<div>
                        <label class="block text-sm font-semibold text-slate-800" for="product_code">Código interno / SKU</label>
                        <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="product_code" name="product_code" type="text" value="{{ old('product_code', $product->product_code) }}" placeholder="Opcional">
                    </div>@endif

                    @if ($showBarcode)<div>
                        <label class="block text-sm font-semibold text-slate-800" for="barcode">Barcode / EAN / GTIN</label>
                        <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="barcode" name="barcode" type="text" inputmode="numeric" value="{{ old('barcode', $product->barcode) }}" placeholder="Ej: 7501234567890">
                        <p class="mt-1 text-xs text-slate-500">Se usa para consultar Open Beauty Facts. No reemplaza tu SKU interno.</p>
                        @error('barcode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>@endif

                    @if ($showBrand)<div>
                        <label class="block text-sm font-semibold text-slate-800" for="brand">Marca</label>
                        <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="brand" name="brand" type="text" value="{{ old('brand', $product->brand) }}" placeholder="Ej: Rasasi, Nike, Samsung">
                    </div>@endif

                    @if (!$product->exists)
                        <section class="rounded-2xl border border-blue-200 bg-blue-50/40 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">Foto del {{ strtolower($productLabel) }}</h2>
                                    <p class="mt-1 text-xs text-slate-600">Sube una imagen cuadrada o búscala por el nombre. La optimizamos automáticamente al publicar.</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-blue-700">WebP optimizado</span>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-[180px_1fr]">
                                <div class="aspect-square overflow-hidden rounded-xl border border-dashed border-blue-300 bg-white">
                                    <template x-if="imagePreview || imageSearch.selected">
                                        <img class="h-full w-full object-cover" :src="imagePreview || imageSearch.selected?.thumbnail" alt="Vista previa del producto">
                                    </template>
                                    <div x-show="!imagePreview && !imageSearch.selected" class="flex h-full flex-col items-center justify-center px-4 text-center text-xs text-slate-500">
                                        <span class="text-3xl">📷</span>
                                        <span class="mt-2">Aún no has elegido una foto</span>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <input class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/avif" @change="imagePreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; imageSearch.selected = null">
                                    <p class="text-[11px] text-slate-500">JPG, PNG, WEBP o AVIF · máximo {{ config('catalog.uploads.max_file_size_mb', 10) }} MB.</p>
                                    <div class="flex items-center gap-2 text-xs text-slate-400"><span class="h-px flex-1 bg-slate-200"></span><span>o busca en la web</span><span class="h-px flex-1 bg-slate-200"></span></div>
                                    <div class="flex gap-2">
                                        <input class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" type="search" x-model="imageSearch.query" @keydown.enter.prevent="searchImages()" placeholder="Ej. perfume azul 100 ml">
                                        <button class="rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 disabled:opacity-60" type="button" @click="searchImages()" :disabled="imageSearch.loading">
                                            <span x-show="!imageSearch.loading">Buscar</span><span x-show="imageSearch.loading" x-cloak>Buscando…</span>
                                        </button>
                                    </div>
                                    <input type="hidden" name="image_source_url" :value="imageSearch.selected?.url || ''">
                                    <p x-show="imageSearch.error" x-text="imageSearch.error" class="text-xs font-semibold text-rose-600" x-cloak></p>
                                </div>
                            </div>

                            <div x-show="imageSearch.results.length" class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6" x-cloak>
                                <template x-for="result in imageSearch.results" :key="result.id + result.url">
                                    <button type="button" class="group overflow-hidden rounded-xl border border-slate-200 bg-white text-left transition hover:border-blue-500 hover:ring-2 hover:ring-blue-100" :class="imageSearch.selected?.url === result.url ? 'border-blue-600 ring-2 ring-blue-200' : ''" @click="imageSearch.selected = result; imagePreview = null">
                                        <img class="aspect-square w-full object-cover" :src="result.thumbnail" :alt="result.title" loading="lazy">
                                        <span class="block truncate px-2 py-1.5 text-[10px] font-semibold text-slate-600" x-text="result.source || 'Openverse'"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="mt-3 text-[11px] text-slate-500">Las imágenes se consultan mediante Openverse y se descargan al servidor para optimizarlas; selecciona solo material que puedas usar comercialmente.</p>
                            @error('image') <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                            @error('image_source_url') <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </section>
                    @endif

                    <!-- Precios: Venta y Costo (Contabilidad de Inventario) -->
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800" for="price">Precio de Venta al Público (RD$) *</label>
                            <p class="text-[11px] text-slate-500">Precio visible para los clientes en la vitrina.</p>
                            <div class="relative mt-1.5 rounded-md shadow-sm">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">RD$</span>
                                <input class="w-full rounded-md border border-slate-300 pl-12 pr-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="price" name="price" step="0.01" min="0" type="number" value="{{ old('price', $product->price) }}" placeholder="0.00">
                            </div>
                        </div>

                        @if ($showWholesale)<div>
                            <label class="block text-sm font-semibold text-slate-800" for="wholesale_price">Precio por mayor (RD$)</label>
                            <p class="text-[11px] text-slate-500">Opcional; se usa en ventas mayoristas y no puede superar el precio regular.</p>
                            <div class="relative mt-1.5 rounded-md shadow-sm">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">RD$</span>
                                <input class="w-full rounded-md border border-slate-300 pl-12 pr-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="wholesale_price" name="wholesale_price" step="0.01" min="0" max="99999999.99" type="number" value="{{ old('wholesale_price', $product->wholesale_price) }}" placeholder="Opcional">
                            </div>
                            @error('wholesale_price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>@endif

                        @if ($showInventory)<div>
                            <label class="block text-sm font-semibold text-slate-800" for="cost_price">
                                Precio de Compra / Costo (RD$)
                                <span class="text-xs font-normal text-slate-500">(Privado)</span>
                            </label>
                            <p class="text-[11px] text-slate-500">Solo tú lo ves. Para calcular valor de inventario y margen.</p>
                            <div class="relative mt-1.5 rounded-md shadow-sm">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">RD$</span>
                                <input class="w-full rounded-md border border-slate-300 pl-12 pr-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="cost_price" name="cost_price" step="0.01" min="0" type="number" value="{{ old('cost_price', $product->inventory?->cost_price) }}" placeholder="0.00">
                            </div>
                        </div>@endif
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <p class="text-sm font-bold text-slate-900">Oferta (opcional)</p>
                        <p class="mt-0.5 text-xs text-slate-600">El precio regular se conserva para mostrar el descuento en la vitrina.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="sale_price">Precio oferta</label>
                                <input class="mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" id="sale_price" name="sale_price" type="number" step="0.01" min="0" value="{{ old('sale_price', $product->sale_price) }}" placeholder="RD$ 0.00">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="sale_starts_at">Desde</label>
                                <input class="mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" id="sale_starts_at" name="sale_starts_at" type="datetime-local" value="{{ old('sale_starts_at', $product->sale_starts_at?->format('Y-m-d\\TH:i')) }}">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="sale_ends_at">Hasta</label>
                                <input class="mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" id="sale_ends_at" name="sale_ends_at" type="datetime-local" value="{{ old('sale_ends_at', $product->sale_ends_at?->format('Y-m-d\\TH:i')) }}">
                            </div>
                        </div>
                        @error('sale_price') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('sale_ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($showPerfumePresentation)
                    <!-- Presentación y unidad de venta -->
                    <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 sm:p-5">
                        <div>
                            <label class="block text-sm font-bold text-slate-900" for="sale_unit">¿Cómo se vende este producto?</label>
                            <p class="mt-0.5 text-xs text-slate-600">Define si el inventario se cuenta por botella, ml o decant.</p>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <select class="w-full rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="sale_unit" name="sale_unit" x-model="saleUnit">
                                    <option value="unit">Unidad</option>
                                    <option value="bottle">Botella completa</option>
                                    <option value="ml">Por mililitro (ml)</option>
                                    <option value="decant">Decant</option>
                                </select>
                            </div>

                            <div x-show="saleUnit === 'bottle' || saleUnit === 'ml' || saleUnit === 'decant'" x-cloak>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="volume_ml">Contenido en ml</label>
                                <input class="mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="volume_ml" name="volume_ml" type="number" min="1" max="100000" value="{{ old('volume_ml', $product->volume_ml) }}" :required="saleUnit === 'bottle' || saleUnit === 'ml' || saleUnit === 'decant'">
                                <p class="mt-1 text-[11px] text-slate-500" x-text="saleUnit === 'ml' ? 'Capacidad inicial del recipiente para mostrar el nivel restante.' : (saleUnit === 'decant' ? 'Ejemplo: 5 ml por decant.' : 'Ejemplo: 100 ml la botella.')"></p>
                            </div>
                        </div>

                        <div class="mt-4" x-show="saleUnit === 'decant'" x-cloak>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="inventory_source_product_id">Botella fuente</label>
                            <select class="mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="inventory_source_product_id" name="inventory_source_product_id" :required="saleUnit === 'decant'">
                                <option value="">Selecciona la botella que se descontará</option>
                                @foreach ($sourceProducts as $sourceProduct)
                                    <option value="{{ $sourceProduct->id }}" @selected(old('inventory_source_product_id', $product->inventory_source_product_id) == $sourceProduct->id)>
                                        {{ $sourceProduct->name }}{{ $sourceProduct->volume_ml ? ' · '.$sourceProduct->volume_ml.' ml' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">Cada venta de este decant descontará sus ml de la botella seleccionada.</p>
                        </div>
                    </div>
                    @endif

                    <!-- Slug -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-800" for="slug">Slug / Enlace amigable (opcional)</label>
                        <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="slug" name="slug" type="text" value="{{ old('slug', $product->slug) }}" placeholder="autogenerado-si-se-deja-vacio">
                    </div>

                    <!-- Categorías -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800" for="global_category_id">Categoría Global</label>
                            <select class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="global_category_id" name="global_category_id">
                                <option value="">Sin categoría global</option>
                                @foreach ($globalCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('global_category_id', $product->global_category_id) == $cat->id)>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-semibold text-slate-800" for="shop_category_id">Categoría de la Tienda</label>
                                <a class="text-xs font-semibold text-blue-700 hover:text-blue-900" href="{{ route('seller.shops.categories.index', $shop) }}" target="_blank">+ Crear</a>
                            </div>
                            <select class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="shop_category_id" name="shop_category_id">
                                <option value="">Sin categoría interna</option>
                                @foreach ($shopCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('shop_category_id', $product->shop_category_id) == $cat->id)>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @php
                        $savedAttributeValues = $product->attributeValues->keyBy('attribute_definition_id');
                        $attributeRows = $attributeDefinitions->map(fn ($definition) => [
                            'name' => $definition->name,
                            'value' => $savedAttributeValues->get($definition->id)?->value ?? '',
                            'filterable' => (bool) $definition->filterable,
                        ])->values();
                    @endphp

                    <!-- Atributos dinámicos -->
                    <section x-data="{ rows: @js($attributeRows) }" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Características del producto</h2>
                                <p class="mt-1 text-xs text-slate-500">Crea atributos como marca, talla, concentración o almacenamiento. Se podrán usar como filtros en la vitrina.</p>
                            </div>
                            <button type="button" @click="rows.push({ name: '', value: '', filterable: true })" class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-50">+ Agregar atributo</button>
                        </div>
                        <div class="mt-4 space-y-2">
                            <template x-for="(row, index) in rows" :key="index">
                                <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-center">
                                    <input type="text" :name="`attributes[${index}][name]`" x-model="row.name" placeholder="Nombre (ej. Talla)" maxlength="100" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-indigo-500 focus:outline-none">
                                    <input type="text" :name="`attributes[${index}][value]`" x-model="row.value" placeholder="Valor (ej. M)" maxlength="255" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-indigo-500 focus:outline-none">
                                    <label class="inline-flex items-center gap-1.5 px-1 text-[11px] font-semibold text-slate-600"><input type="checkbox" :name="`attributes[${index}][filterable]`" value="1" x-model="row.filterable" class="rounded border-slate-300 text-indigo-600"> Filtrar</label>
                                    <button type="button" @click="rows.splice(index, 1)" class="justify-self-end rounded-lg p-2 text-slate-400 hover:bg-white hover:text-rose-600" title="Quitar atributo">✕</button>
                                </div>
                            </template>
                        </div>
                    </section>

                    <!-- Estados: Disponibilidad y Moderación -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800" for="availability_status">Disponibilidad</label>
                            <select class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="availability_status" name="availability_status" required>
                                <option value="available" @selected(old('availability_status', $product->availability_status?->value ?? 'available') === 'available')>Disponible para pedidos</option>
                                <option value="out_of_stock" @selected(old('availability_status', $product->availability_status?->value) === 'out_of_stock')>Agotado / Sin stock</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800" for="moderation_status">Estado de publicación</label>
                            <select class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="moderation_status" name="moderation_status" required>
                                <option value="active" @selected(old('moderation_status', $product->moderation_status?->value ?? 'active') === 'active')>Activo (Visible en vitrina)</option>
                                <option value="draft" @selected(old('moderation_status', $product->moderation_status?->value) === 'draft')>Borrador (Oculto al público)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Control de Inventario (Inventory Lite) -->
                    @if ($showInventory)<div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-bold text-slate-900 flex items-center gap-2 cursor-pointer" for="track_inventory">
                                    <input
                                        type="checkbox"
                                        id="track_inventory"
                                        name="track_inventory"
                                        value="1"
                                        x-model="trackInventory"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    Controlar inventario y stock de este producto
                                </label>
                                <p class="text-xs text-slate-500 mt-0.5 ml-6">
                                    Permite registrar ventas, reposiciones, alertas de poco stock y agotado automático en la vitrina.
                                </p>
                            </div>
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800 uppercase tracking-wider">Inventory Lite</span>
                        </div>

                        <div x-show="trackInventory" class="mt-4 grid gap-4 sm:grid-cols-2 pt-3 border-t border-slate-200/80">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="stock_quantity">
                                    <span x-text="saleUnit === 'ml' ? 'Stock disponible (ml)' : (saleUnit === 'bottle' ? 'Botellas disponibles' : (saleUnit === 'decant' ? 'Decants disponibles' : '{{ $product->exists ? 'Stock actual' : 'Stock inicial' }}'))"></span>
                                </label>
                                <p class="text-[11px] text-slate-500" x-text="saleUnit === 'ml' ? 'Cantidad total de ml disponibles.' : (saleUnit === 'decant' ? 'Se calcula automáticamente según la botella fuente.' : 'Unidades físicas disponibles para la venta.')"></p>
                                <input
                                    type="number"
                                    id="stock_quantity"
                                    name="stock_quantity"
                                    min="0"
                                    value="{{ old('stock_quantity', $product->inventory?->stock_quantity ?? 0) }}"
                                    :disabled="saleUnit === 'decant'"
                                    class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 font-mono font-bold focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                >
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="low_stock_threshold">
                                    Alerta de Stock Mínimo
                                </label>
                                <p class="text-[11px] text-slate-500">Avisar cuando queden igual o menos de estas unidades.</p>
                                <input
                                    type="number"
                                    id="low_stock_threshold"
                                    name="low_stock_threshold"
                                    min="0"
                                    value="{{ old('low_stock_threshold', $product->inventory?->low_stock_threshold ?? 3) }}"
                                    class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 font-mono focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                >
                            </div>
                        </div>
                    </div>@endif

                    <!-- Descripción -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-800" for="description">Descripción</label>
                        <textarea class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="description" name="description" rows="4" placeholder="Describe los detalles, tallas, variantes o especificaciones...">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-800" for="notes">Notas internas</label>
                        <textarea class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="notes" name="notes" rows="3" placeholder="Notas del inventario o información privada...">{{ old('notes', $product->notes) }}</textarea>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                        <a class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('seller.shops.products.index', $shop) }}">
                            Cancelar
                        </a>
                        <button class="rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2" type="submit">
                            {{ $product->exists ? 'Guardar cambios' : 'Publicar producto' }}
                        </button>
                    </div>
                </form>
            </section>

            @if ($product->exists)
                <section class="mt-6 rounded-xl border border-blue-200 bg-blue-50/50 p-6 shadow-sm sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-blue-100 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Existencias</h2>
                            <p class="mt-1 text-xs text-slate-600">Consulta y agrega unidades sin reemplazar el stock actual.</p>
                        </div>
                        @if ($product->inventory?->track_inventory)
                            <a href="{{ route('seller.shops.inventory.movements', [$shop, $product]) }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Ver movimientos</a>
                        @endif
                    </div>

                    @if ($product->inventory?->track_inventory)
                        <div class="mt-5 flex flex-wrap items-end gap-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Disponible ahora</p>
                                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($product->inventory->stock_quantity) }} <span class="text-sm font-semibold text-slate-500">unidades</span></p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format($product->inventory->sold_quantity) }} vendidas · alerta en {{ $product->inventory->low_stock_threshold }}</p>
                            </div>

                            <form class="flex flex-1 flex-wrap items-end gap-3" method="POST" action="{{ route('seller.shops.inventory.restock', [$shop, $product]) }}">
                                @csrf
                                <div class="min-w-36 flex-1">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="restock_quantity">Agregar unidades</label>
                                    <input class="mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm font-bold text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="restock_quantity" name="quantity" type="number" min="1" max="100000" value="1" required>
                                </div>
                                <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700" type="submit">Agregar al stock</button>
                            </form>
                        </div>
                    @else
                        <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            El control de inventario está desactivado. Actívalo arriba y guarda los cambios para comenzar a registrar existencias y reposiciones.
                        </p>
                    @endif
                </section>
            @endif

            @if ($product->exists)
                <!-- Galería y Gestión de Imágenes -->
                <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="border-b border-slate-200 pb-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">Fotos del producto</h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $product->images->count() }} de {{ $shop->imageLimit() }} fotos permitidas en el plan {{ $shop->planLabel() }}.
                                </p>
                            </div>
                            <span class="rounded bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">WebP optimizado</span>
                        </div>
                    </div>

                    <!-- Lista de imágenes existentes -->
                    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        @forelse ($product->images as $image)
                            <div class="group relative overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <div class="aspect-square w-full overflow-hidden bg-slate-100">
                                    <img class="h-full w-full object-cover" src="{{ $image->thumbnail_url }}" alt="{{ $product->name }}">
                                </div>
                                <div class="p-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        @if ($image->processing_status->value === 'ready')
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i> Lista
                                            </span>
                                        @elseif ($image->processing_status->value === 'processing' || $image->processing_status->value === 'pending')
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></i> Procesando
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700">
                                                <i class="h-1.5 w-1.5 rounded-full bg-red-500"></i> Falló
                                            </span>
                                        @endif

                                        <form method="POST" action="{{ route('seller.shops.products.images.destroy', [$shop, $product, $image]) }}" onsubmit="return confirm('¿Eliminar esta foto?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs font-semibold text-red-600 hover:text-red-800" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                                Este producto aún no tiene fotos. Sube una a continuación.
                            </div>
                        @endforelse
                    </div>

                    <!-- Formulario de subida -->
                    @if ($product->images->count() < config('catalog.free.max_images_per_product', 3))
                        <form class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4" method="POST" action="{{ route('seller.shops.products.images.store', [$shop, $product]) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700" for="image">Agregar nueva foto</label>
                            <p class="mt-0.5 text-xs text-slate-500">JPG, PNG o WEBP hasta 10 MB. Se optimizará y convertirá a WebP automáticamente.</p>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <input class="text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700" id="image" name="image" required type="file" accept="image/jpeg,image/png,image/webp,image/avif">
                                <button class="rounded-md bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" type="submit">Subir y optimizar</button>
                            </div>
                        </form>
                    @else
                        <p class="mt-4 rounded-md bg-amber-50 p-3 text-center text-xs font-medium text-amber-800">
                            Has alcanzado el límite máximo de {{ config('catalog.free.max_images_per_product', 3) }} fotos para este producto.
                        </p>
                    @endif

                    <section class="mt-5 rounded-xl border border-blue-200 bg-blue-50/40 p-4" x-data="{ query: '', results: [], loading: false, error: '', endpoint: '{{ route('seller.shops.products.images.search', $shop) }}', async search() { if (this.query.trim().length < 2) { this.error = 'Escribe al menos 2 caracteres.'; return; } this.loading = true; this.error = ''; try { const response = await fetch(`${this.endpoint}?q=${encodeURIComponent(this.query.trim())}`, { headers: { Accept: 'application/json' } }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'No se pudo buscar.'); this.results = payload.results || []; if (!this.results.length) this.error = 'No encontramos resultados para esa búsqueda.'; } catch (error) { this.error = error.message; } finally { this.loading = false; } } }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Buscar una foto en la web</h3>
                                <p class="mt-1 text-xs text-slate-600">Busca por nombre y agrégala sin reemplazar tus fotos.</p>
                            </div>
                            <span class="text-[11px] font-semibold text-blue-700">Openverse</span>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <input class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" type="search" x-model="query" @keydown.enter.prevent="search()" placeholder="Ej. perfume Rasasi">
                            <button class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-60" type="button" @click="search()" :disabled="loading"><span x-show="!loading">Buscar</span><span x-show="loading" x-cloak>Buscando…</span></button>
                        </div>
                        <p x-show="error" x-text="error" class="mt-2 text-xs font-semibold text-rose-600" x-cloak></p>
                        <div x-show="results.length" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-6" x-cloak>
                            <template x-for="result in results" :key="result.id + result.url">
                                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                    <img class="aspect-square w-full object-cover" :src="result.thumbnail" :alt="result.title" loading="lazy">
                                    <form class="p-2" method="POST" action="{{ route('seller.shops.products.images.web.store', [$shop, $product]) }}">
                                        @csrf
                                        <input type="hidden" name="url" :value="result.url">
                                        <button class="w-full rounded-md border border-blue-200 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-50" type="submit">Agregar foto</button>
                                    </form>
                                </div>
                            </template>
                        </div>
                        <p class="mt-3 text-[11px] text-slate-500">Verifica que tengas permiso para usar la imagen antes de publicarla.</p>
                    </section>
                </section>

                @if ($product->barcode)
                    <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50/50 p-6 shadow-sm sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">Biblioteca Open Beauty Facts</h2>
                                <p class="mt-1 text-xs text-slate-600">Consulta datos e imágenes por barcode. Nada reemplaza tus fotos manuales.</p>
                            </div>
                            <form method="POST" action="{{ route('seller.shops.products.catalog-media.resolve', [$shop, $product]) }}">
                                @csrf
                                <button class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800" type="submit">Consultar ahora</button>
                            </form>
                        </div>

                        @if ($product->catalogProduct)
                            <div class="mt-4 rounded-lg border border-emerald-100 bg-white p-4 text-sm">
                                <p class="font-bold text-slate-900">{{ $product->catalogProduct->name ?: 'Producto encontrado' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Estado: {{ $product->catalogProduct->lookup_status }} · Última consulta: {{ $product->catalogProduct->last_lookup_at?->diffForHumans() ?: 'pendiente' }}</p>
                                @if ($product->catalogProduct->last_error)
                                    <p class="mt-2 text-xs text-amber-700">{{ $product->catalogProduct->last_error }}</p>
                                @endif
                            </div>
                            @if ($product->catalogProduct->images->where('processing_status', 'ready')->isNotEmpty())
                                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                                    @foreach ($product->catalogProduct->images->where('processing_status', 'ready') as $catalogImage)
                                        <div class="overflow-hidden rounded-lg border border-emerald-100 bg-white">
                                            <img class="aspect-square w-full object-cover" src="{{ $catalogImage->thumbnail_url }}" alt="Imagen externa de {{ $product->name }}">
                                            <div class="p-3">
                                                <p class="text-[11px] text-slate-500">{{ $catalogImage->license ?: 'Licencia no informada por el proveedor' }}</p>
                                                <form class="mt-2" method="POST" action="{{ route('seller.shops.products.catalog-media.use', [$shop, $product, $catalogImage->id]) }}">
                                                    @csrf
                                                    <button class="w-full rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-800 hover:bg-emerald-50" type="submit">Agregar sin reemplazar</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <p class="mt-4 rounded-lg border border-dashed border-emerald-200 bg-white p-4 text-xs text-slate-600">Aún no hay resultado local. La consulta se ejecuta en segundo plano cuando haya un worker activo.</p>
                        @endif
                    </section>
                @endif
            @endif
        </div>
    </main>
</x-layouts.app>
