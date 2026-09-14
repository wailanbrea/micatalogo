<x-layouts.app :title="($product->exists ? 'Editar producto' : 'Nuevo producto') . ' | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => $product->exists ? 'Editar ' . $product->name : 'Nuevo producto']
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl">

            <section class="mt-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="border-b border-slate-200 pb-4">
                    <h1 class="text-2xl font-bold text-slate-900">
                        {{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}
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

                <form class="mt-6 space-y-6" method="POST" action="{{ $product->exists ? route('seller.shops.products.update', [$shop, $product]) : route('seller.shops.products.store', $shop) }}">
                    @csrf
                    @if ($product->exists)
                        @method('PUT')
                    @endif

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-800" for="name">Nombre del producto *</label>
                        <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="name" name="name" required type="text" value="{{ old('name', $product->name) }}" placeholder="Ej: Zapatillas Urbanas Pro">
                    </div>

                    <!-- Slug y Precio -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800" for="price">Precio (RD$) *</label>
                            <div class="relative mt-1.5 rounded-md shadow-sm">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">RD$</span>
                                <input class="w-full rounded-md border border-slate-300 pl-12 pr-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="price" name="price" step="0.01" min="0" type="number" value="{{ old('price', $product->price) }}" placeholder="0.00">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800" for="slug">Slug (opcional)</label>
                            <input class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="slug" name="slug" type="text" value="{{ old('slug', $product->slug) }}" placeholder="autogenerado-si-se-deja-vacio">
                        </div>
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

                    <!-- Descripción -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-800" for="description">Descripción</label>
                        <textarea class="mt-1.5 w-full rounded-md border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="description" name="description" rows="4" placeholder="Describe los detalles, tallas, variantes o especificaciones...">{{ old('description', $product->description) }}</textarea>
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
                <!-- Galería y Gestión de Imágenes -->
                <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="border-b border-slate-200 pb-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">Fotos del producto</h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $product->images->count() }} de {{ config('catalog.free.max_images_per_product', 3) }} fotos permitidas en el plan gratuito.
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
                </section>
            @endif
        </div>
    </main>
</x-layouts.app>
