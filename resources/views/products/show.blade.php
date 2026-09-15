@php
    $productUrl = url()->current();
    $waMessage = "Hola, me interesa \"{$product->name}\" que vi en MiCatalogo:\n{$productUrl}";
    $waUrl = "https://wa.me/{$shop->whatsapp_country_code}{$shop->whatsapp_number}?text=".rawurlencode($waMessage);
@endphp

<x-layouts.app :title="$product->name.' | '.$shop->name.' en MiCatalogo'">
    <div class="min-h-screen bg-[#F5F7FA] text-slate-800">
        <!-- Top Nav -->
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-[1200px] items-center justify-between px-4 py-3 sm:px-8">
                <a class="flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:text-blue-800 sm:text-sm shrink-0" href="{{ route('shops.show', $shop) }}">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Volver a {{ $shop->name }}</span>
                </a>
                <div class="flex items-center gap-2 sm:gap-3">
                    <span class="text-xs text-slate-400 hidden md:inline font-medium">Powered by <a href="{{ route('home') }}" class="font-bold text-slate-600 hover:text-blue-600 transition">MiCatalogo</a></span>
                    @auth
                        @can('update', $product)
                            <a class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition shadow-2xs inline-flex items-center gap-1.5" href="{{ route('seller.shops.products.edit', [$shop, $product]) }}">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span class="hidden xs:inline">Editar</span>
                            </a>
                        @endcan
                        @if (auth()->user()->isAdmin())
                            <a class="rounded-md bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 border border-rose-200" href="{{ route('admin.dashboard') }}">
                                Admin
                            </a>
                        @endif
                        <a class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200" href="{{ route('seller.dashboard') }}">
                            Panel
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-rose-600 transition ml-1 cursor-pointer" title="Cerrar sesión">
                                Salir
                            </button>
                        </form>
                    @else
                        <a class="hidden sm:inline-block text-xs font-semibold text-slate-600 hover:text-slate-900" href="{{ route('login') }}">
                            Iniciar sesión
                        </a>
                        <a class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition shrink-0 whitespace-nowrap" href="{{ route('register') }}">
                            Crear tienda
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1300px] px-4 py-6 sm:px-6 lg:px-8 pb-24 sm:pb-8">
            <!-- Breadcrumbs (Scoped to this Shop) -->
            <nav class="mb-5 flex items-center gap-2 text-xs text-slate-500 overflow-x-auto whitespace-nowrap" aria-label="Ruta de navegación">
                <a class="font-bold text-blue-700 hover:text-blue-900 transition" href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a>
                @if ($product->shopCategory)
                    <span class="text-slate-300">/</span>
                    <a class="hover:text-slate-800 transition" href="{{ route('shops.show', [$shop, 'categoria' => $product->shopCategory->slug]) }}">{{ $product->shopCategory->name }}</a>
                @endif
                <span class="text-slate-300">/</span>
                <span class="truncate font-medium text-slate-800">{{ $product->name }}</span>
            </nav>

            <!-- Amazon-Style Product Layout: 3 Columns on Desktop, 2 on Tablet, Stacked on Mobile -->
            <div class="grid grid-cols-1 gap-8 md:grid-cols-12 lg:gap-10">
                <!-- Col 1: Gallery Section (Mobile: full, Tablet: 6 cols, Desktop: 5 cols) -->
                <div class="md:col-span-6 lg:col-span-5">
                    <div class="md:sticky md:top-20 space-y-3">
                        <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
                            @if ($product->images->isNotEmpty())
                                <img id="main-product-image" src="{{ $product->images->first()->url }}" alt="{{ $product->name }}" class="h-full w-full object-contain object-center transition duration-200">
                            @else
                                <svg class="h-24 w-24 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                            @endif
                        </div>

                        <!-- Thumbnails -->
                        @if ($product->images->count() > 1)
                            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                                @foreach ($product->images as $index => $img)
                                    <button class="gallery-thumb relative h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 bg-white transition cursor-pointer {{ $loop->first ? 'border-blue-600 ring-2 ring-blue-600/20' : 'border-slate-200 hover:border-slate-300' }}" data-index="{{ $index }}" data-src="{{ $img->url }}" onclick="selectGalleryImage(this, '{{ $img->url }}')" type="button">
                                        <img src="{{ $img->url }}" alt="{{ $product->name }} foto {{ $loop->iteration }}" class="h-full w-full object-cover object-center">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Col 2: Center Details (Mobile: full, Tablet: 6 cols, Desktop: 4 cols) -->
                <div class="md:col-span-6 lg:col-span-4 flex flex-col space-y-4">
                    <!-- Store Link (Amazon Style "Visita la tienda...") -->
                    <div class="flex items-center justify-between">
                        <a class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:underline" href="{{ route('shops.show', $shop) }}">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-800">
                                {{ str($shop->name)->substr(0, 1)->upper() }}
                            </span>
                            <span>Visita la tienda {{ $shop->name }}</span>
                        </a>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Catálogo Verificado
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 leading-tight">
                        {{ $product->name }}
                    </h1>

                    @php($isUnavailable = $product->isInventoryTracked()
                        ? $product->inventory->isOutOfStock()
                        : $product->availability_status->value === 'out_of_stock')

                    <!-- Stock / Availability Badge -->
                    <div>
                        @if ($product->isInventoryTracked())
                            @if ($isUnavailable)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 border border-rose-200">
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span> No disponible por el momento
                                </span>
                            @elseif ($product->inventory->isLowStock())
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 border border-amber-200">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span> ¡Últimas {{ $product->inventory->stock_quantity }} unidades!
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> En stock
                                </span>
                            @endif
                        @elseif ($product->availability_status->value === 'available')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span> En stock
                            </span>
                        @elseif ($product->availability_status->value === 'out_of_stock')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 border border-rose-200">
                                <span class="h-2 w-2 rounded-full bg-rose-500"></span> Agotado
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 border border-amber-200">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span> Bajo pedido
                            </span>
                        @endif
                    </div>

                    <!-- Price Block -->
                    <div class="border-y border-slate-200/80 py-3">
                        <div class="flex items-baseline gap-2">
                            <span class="text-xs font-semibold text-slate-500">Precio:</span>
                            <span class="text-3xl font-black tracking-tight text-slate-900">
                                RD$ {{ number_format((float) $product->price, 0) }}
                            </span>
                            <span class="text-xs font-medium text-slate-500">DOP</span>
                        </div>
                    </div>

                    <!-- Amazon-Style "Acerca de este producto" Feature Bullets -->
                    <div class="space-y-2 pt-1">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Acerca de este producto</h2>
                        <ul class="space-y-2 text-xs text-slate-700">
                            <li class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span><strong>Compra directa:</strong> Contacto directo con el vendedor por WhatsApp, sin comisiones ni intermediarios.</span>
                            </li>
                            @if ($shop->offers_shipping)
                                <li class="flex items-start gap-2.5">
                                    <svg class="h-4 w-4 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                    <span><strong>Envíos disponibles:</strong> Esta tienda realiza envíos en República Dominicana a coordinar directamente.</span>
                                </li>
                            @endif
                            <li class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><strong>Atención al instante:</strong> Tu mensaje incluirá la referencia exacta y enlace para confirmación rápida.</span>
                            </li>
                        </ul>
                    </div>

                    @if ($product->description)
                        <div class="border-t border-slate-200/80 pt-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Descripción</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                                {{ $product->description }}
                            </p>
                        </div>
                    @endif
                </div>

                <!-- Col 3: Dedicated Amazon-Style Buy Box (Desktop: 3 cols, Tablet: full width, Mobile: in flow) -->
                <div class="md:col-span-12 lg:col-span-3">
                    <div class="lg:sticky lg:top-20 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
                        <div class="space-y-1">
                            <span class="text-xs text-slate-500">Total a pagar:</span>
                            <p class="text-2xl font-black text-slate-900">
                                RD$ {{ number_format((float) $product->price, 0) }}
                            </p>
                        </div>

                        <!-- Availability & Delivery Line -->
                        <div class="space-y-1 text-xs">
                            @if ($isUnavailable)
                                <p class="font-bold text-rose-600">No disponible por el momento</p>
                            @else
                                <p class="font-bold text-emerald-700">✓ En stock y listo para ordenar</p>
                            @endif

                            @if ($shop->offers_shipping)
                                <p class="text-slate-600">Envíos a convenir con la tienda</p>
                            @else
                                <p class="text-slate-500">Retiro / entrega a convenir</p>
                            @endif
                        </div>

                        <!-- Primary CTA Button -->
                        <div>
                            @if ($isUnavailable)
                                <span class="flex w-full items-center justify-center rounded-xl bg-slate-200 px-5 py-3.5 text-sm font-bold text-slate-500">Producto no disponible</span>
                            @else
                            <a class="flex w-full items-center justify-center gap-2.5 rounded-xl bg-emerald-600 px-5 py-3.5 text-sm font-bold text-white shadow-md transition duration-150 hover:bg-emerald-700 active:scale-[0.98] text-center" href="{{ route('track.wa.product', [$shop, $product]) }}" data-wa-target="{{ $waUrl }}" rel="noopener noreferrer" target="_blank">
                                <svg class="h-5 w-5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                <span>Consultar por WhatsApp</span>
                            </a>
                            <p class="mt-2 text-center text-[11px] text-slate-500">
                                Serás redirigido a WhatsApp directamente con el vendedor.
                            </p>
                            @endif
                        </div>

                        <!-- Amazon Guarantee Trust Indicators -->
                        <div class="border-t border-slate-100 pt-3 space-y-1.5 text-[11px] text-slate-500">
                            <div class="flex items-center justify-between">
                                <span>Vendido por</span>
                                <span class="font-semibold text-slate-800">{{ $shop->name }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Canal de compra</span>
                                <span class="font-semibold text-emerald-700">WhatsApp Oficial</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Seguridad</span>
                                <span class="text-slate-700">Trato directo</span>
                            </div>
                        </div>

                        <!-- Actions: Share & Report -->
                        <div class="border-t border-slate-100 pt-3 flex items-center justify-between text-xs">
                            <button class="inline-flex items-center gap-1.5 font-semibold text-slate-600 hover:text-slate-900 cursor-pointer transition" onclick="shareProduct('{{ $product->name }}', '{{ $productUrl }}')" type="button">
                                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                <span>Compartir producto</span>
                            </button>
                            <x-report-modal type="product" :id="$product->public_id" :name="$product->name" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Purchase Bar for Mobile Screens (Amazon Mobile Pattern) -->
            <div class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 px-4 py-2.5 shadow-2xl flex items-center justify-between gap-3 sm:hidden">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100 border border-slate-200">
                        @if ($product->images->isNotEmpty())
                            <img src="{{ $product->images->first()->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-slate-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-900 truncate">{{ $product->name }}</p>
                        <p class="text-sm font-black text-emerald-700">RD$ {{ number_format((float) $product->price, 0) }}</p>
                    </div>
                </div>
                <a class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm active:scale-95 text-center" href="{{ route('track.wa.product', [$shop, $product]) }}" data-wa-target="{{ $waUrl }}" rel="noopener noreferrer" target="_blank">
                    <svg class="h-4 w-4 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    <span>WhatsApp</span>
                </a>
            </div>

            <x-ad-slot position="product_below_details" />

            <!-- More products from this shop -->
            @if ($relatedProducts->isNotEmpty())
                <section class="mt-12">
                    <div class="mb-4 flex items-baseline justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Más productos de {{ $shop->name }}</h2>
                        <a class="text-xs font-semibold text-blue-700 hover:underline" href="{{ route('shops.show', $shop) }}">
                            Ver todos los productos →
                        </a>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($relatedProducts as $related)
                            <article class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:shadow-sm">
                                <a class="flex flex-1 flex-col" href="{{ route('products.show', [$shop, $related]) }}">
                                    <div class="relative aspect-square overflow-hidden bg-slate-100">
                                        @if ($related->images->isNotEmpty())
                                            <img src="{{ $related->images->first()->url }}" alt="{{ $related->name }}" class="h-full w-full object-cover object-center group-hover:scale-105 transition duration-200" loading="lazy">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-slate-400">
                                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <h3 class="line-clamp-1 text-xs font-semibold text-slate-800 group-hover:text-blue-600">
                                            {{ $related->name }}
                                        </h3>
                                        <p class="mt-1 text-sm font-bold text-slate-900">
                                            RD$ {{ number_format((float) $related->price, 0) }}
                                        </p>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        <x-shop-footer :shop="$shop" />
    </div>

    <!-- Gallery & Share Scripts -->
    <script>
        function selectGalleryImage(button, src) {
            const mainImg = document.getElementById('main-product-image');
            if (mainImg) {
                mainImg.src = src;
            }
            document.querySelectorAll('.gallery-thumb').forEach(b => {
                b.classList.remove('border-blue-600', 'ring-2', 'ring-blue-600/20');
                b.classList.add('border-slate-200');
            });
            button.classList.remove('border-slate-200');
            button.classList.add('border-blue-600', 'ring-2', 'ring-blue-600/20');
        }

        function shareProduct(name, url) {
            if (navigator.share) {
                navigator.share({
                    title: name + ' en MiCatalogo',
                    text: 'Mira este producto: ' + name,
                    url: url,
                }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Enlace del producto copiado al portapapeles');
                });
            }
        }
    </script>
</x-layouts.app>
