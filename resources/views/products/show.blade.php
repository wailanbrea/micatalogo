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
                <a class="flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:text-blue-800 sm:text-sm" href="{{ route('shops.show', $shop) }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Volver a {{ $shop->name }}</span>
                </a>
                <div class="flex items-center gap-3">
                    <a class="text-sm font-bold tracking-tight text-slate-900 hidden sm:inline" href="{{ route('home') }}">
                        Mi<span class="text-blue-600">Catalogo</span>
                    </a>
                    @auth
                        @can('update', $product)
                            <a class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition shadow-2xs inline-flex items-center gap-1.5" href="{{ route('seller.shops.products.edit', [$shop, $product]) }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span>Editar producto</span>
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
                        <a class="text-xs font-semibold text-slate-600 hover:text-slate-900" href="{{ route('login') }}">
                            Iniciar sesión
                        </a>
                        <a class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition" href="{{ route('register') }}">
                            Crear tienda
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1200px] px-4 py-6 sm:px-8">
            <!-- Breadcrumbs -->
            <nav class="mb-4 flex items-center gap-2 text-xs text-slate-500 overflow-x-auto whitespace-nowrap">
                <a class="hover:text-slate-800" href="{{ route('home') }}">Inicio</a>
                <span>/</span>
                <a class="hover:text-slate-800" href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a>
                @if ($product->shopCategory)
                    <span>/</span>
                    <a class="hover:text-slate-800" href="{{ route('shops.show', [$shop, 'categoria' => $product->shopCategory->slug]) }}">{{ $product->shopCategory->name }}</a>
                @endif
                <span>/</span>
                <span class="truncate font-medium text-slate-800">{{ $product->name }}</span>
            </nav>

            <!-- Product Detail Card -->
            <article class="grid gap-8 rounded-xl border border-slate-200 bg-white p-5 shadow-xs md:grid-cols-2 sm:p-8">
                <!-- Gallery Section -->
                <div>
                    <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                        @if ($product->images->isNotEmpty())
                            <img id="main-product-image" src="{{ $product->images->first()->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover object-center transition duration-200">
                        @else
                            <svg class="h-24 w-24 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                        @endif
                    </div>

                    <!-- Multiple Image Thumbnails -->
                    @if ($product->images->count() > 1)
                        <div class="mt-3 flex items-center gap-2 overflow-x-auto pb-1">
                            @foreach ($product->images as $index => $img)
                                <button class="gallery-thumb relative h-16 w-16 shrink-0 overflow-hidden rounded-md border-2 transition {{ $loop->first ? 'border-blue-600 ring-2 ring-blue-600/20' : 'border-slate-200 hover:border-slate-300' }}" data-index="{{ $index }}" data-src="{{ $img->url }}" onclick="selectGalleryImage(this, '{{ $img->url }}')" type="button">
                                    <img src="{{ $img->url }}" alt="{{ $product->name }} foto {{ $loop->iteration }}" class="h-full w-full object-cover object-center">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Details Section -->
                <div class="flex flex-col">
                    <div class="flex items-center justify-between">
                        <a class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:underline" href="{{ route('shops.show', $shop) }}">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-blue-800">
                                {{ str($shop->name)->substr(0, 1)->upper() }}
                            </span>
                            {{ $shop->name }}
                        </a>

                        @if ($product->availability_status->value === 'available')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Disponible
                            </span>
                        @elseif ($product->availability_status->value === 'out_of_stock')
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Agotado
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Bajo pedido
                            </span>
                        @endif
                    </div>

                    <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                        {{ $product->name }}
                    </h1>

                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold tracking-tight text-slate-900">
                            RD$ {{ number_format((float) $product->price, 0) }}
                        </span>
                        <span class="text-xs font-medium text-slate-500">DOP</span>
                    </div>

                    @if ($product->description)
                        <div class="mt-6 border-t border-slate-100 pt-5">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Descripción</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">
                                {{ $product->description }}
                            </p>
                        </div>
                    @endif

                    <!-- WhatsApp CTA -->
                    <div class="mt-8 border-t border-slate-100 pt-6">
                        <a class="flex w-full items-center justify-center gap-2.5 rounded-xl bg-emerald-600 px-6 py-3.5 text-base font-bold text-white shadow-md transition duration-150 hover:bg-emerald-700 active:scale-[0.99]" href="{{ route('track.wa.product', [$shop, $product]) }}" data-wa-target="{{ $waUrl }}" rel="noopener noreferrer" target="_blank">
                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            Consultar por WhatsApp
                        </a>
                        <p class="mt-2 text-center text-xs text-slate-500">
                            Serás redirigido a WhatsApp directamente con el vendedor.
                        </p>

                        <!-- Share and Report buttons -->
                        <div class="mt-4 flex items-center justify-between">
                            <button class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900" onclick="shareProduct('{{ $product->name }}', '{{ $productUrl }}')" type="button">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                Compartir este producto
                            </button>
                            <x-report-modal type="product" :id="$product->public_id" :name="$product->name" />
                        </div>
                    </div>
                </div>
            </article>

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

        <x-public-footer />
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
