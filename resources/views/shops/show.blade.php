<x-layouts.app :title="$shop->name.' | Catálogo en MiCatalogo'">
    <div class="min-h-screen bg-[#F5F7FA] text-slate-800">
        <!-- Top Nav -->
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-[1400px] items-center justify-between px-4 py-3 sm:px-8">
                <a class="flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800" href="{{ route('home') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Explorar MiCatalogo</span>
                </a>
                <a class="text-sm font-bold tracking-tight text-slate-900" href="{{ route('home') }}">
                    Mi<span class="text-blue-600">Catalogo</span>
                </a>
                <div class="flex items-center gap-3">
                    @auth
                        @can('update', $shop)
                            <a class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition shadow-2xs inline-flex items-center gap-1.5" href="{{ route('seller.shops.products.index', $shop) }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Administrar tienda</span>
                            </a>
                        @endcan
                        @if (auth()->user()->isAdmin())
                            <a class="rounded-md bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 border border-rose-200" href="{{ route('admin.dashboard') }}">
                                Panel Admin
                            </a>
                        @endif
                        <a class="rounded-md bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200" href="{{ route('seller.dashboard') }}">
                            Mis tiendas
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
                            Crear tienda gratis
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <!-- Shop Profile Header -->
        <section class="border-b border-slate-200 bg-white shadow-xs">
            <div class="mx-auto max-w-[1400px] px-4 py-8 sm:px-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        @if ($shop->logo_url)
                            <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-16 w-16 shrink-0 rounded-2xl object-cover shadow-md sm:h-20 sm:w-20 border border-slate-200">
                        @else
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-2xl font-black text-white shadow-md sm:h-20 sm:w-20 sm:text-3xl">
                                {{ str($shop->name)->substr(0, 1)->upper() }}
                            </div>
                        @endif
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $shop->name }}</h1>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Catálogo Verificado
                                </span>
                            </div>
                            @if ($shop->description)
                                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">{{ $shop->description }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                @if ($shop->offers_shipping)
                                    <span class="inline-flex items-center gap-1 text-slate-700">
                                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Envíos disponibles
                                    </span>
                                @endif
                                @if ($shop->instagram)
                                    <a class="inline-flex items-center gap-1 font-medium text-slate-600 hover:text-pink-600" href="https://instagram.com/{{ ltrim($shop->instagram, '@') }}" rel="noopener noreferrer" target="_blank">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                        {{ $shop->instagram }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Direct Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <a class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 active:scale-95" href="{{ route('track.wa.shop', $shop) }}" data-wa-target="https://wa.me/{{ $shop->whatsapp_country_code.$shop->whatsapp_number }}" rel="noopener noreferrer" target="_blank">
                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            Contactar por WhatsApp
                        </a>
                        <button class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50" id="share-btn" onclick="shareStore('{{ $shop->name }}', '{{ url()->current() }}')" type="button">
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            Compartir catálogo
                        </button>
                        <x-report-modal type="shop" :id="$shop->public_id" :name="$shop->name" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area -->
        <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-8">
            <!-- Categories Filter Tabs -->
            @if ($categories->isNotEmpty())
                <div class="mb-6">
                    <div class="flex items-center gap-2 overflow-x-auto pb-2 text-sm font-medium">
                        <a class="whitespace-nowrap rounded-full px-4 py-2 transition {{ !$selectedCategory ? 'bg-blue-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}" href="{{ route('shops.show', $shop) }}">
                            Todos los productos ({{ $shop->products()->where('moderation_status', 'active')->count() }})
                        </a>
                        @foreach ($categories as $category)
                            <a class="whitespace-nowrap rounded-full px-4 py-2 transition {{ $selectedCategory?->id === $category->id ? 'bg-blue-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}" href="{{ route('shops.show', [$shop, 'categoria' => $category->slug]) }}">
                                {{ $category->name }} ({{ $category->products_count }})
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Products Grid Header -->
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">
                        {{ $selectedCategory ? $selectedCategory->name : 'Catálogo de productos' }}
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ $products->total() }} {{ $products->total() === 1 ? 'producto disponible' : 'productos disponibles' }}
                    </p>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                @forelse ($products as $product)
                    <article class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs transition duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-md">
                        <a class="flex flex-1 flex-col" href="{{ route('products.show', [$shop, $product]) }}">
                            <div class="relative aspect-square overflow-hidden bg-slate-100">
                                @if ($product->images->isNotEmpty())
                                    <img src="{{ $product->images->first()->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover object-center transition duration-300 group-hover:scale-105" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-slate-400">
                                        <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Zm5-12h.01" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                    </div>
                                @endif
                                @if ($product->images->count() > 1)
                                    <span class="absolute bottom-2 right-2 rounded-md bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold text-white backdrop-blur-xs">
                                        1/{{ $product->images->count() }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-3.5">
                                <h3 class="line-clamp-2 min-h-10 text-sm font-semibold text-slate-900 group-hover:text-blue-600">
                                    {{ $product->name }}
                                </h3>
                                <div class="mt-2 flex items-baseline justify-between">
                                    <p class="text-lg font-extrabold tracking-tight text-slate-900">
                                        RD$ {{ number_format((float) $product->price, 0) }}
                                    </p>
                                </div>
                                <div class="mt-2">
                                    @if ($product->availability_status->value === 'available')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Disponible
                                        </span>
                                    @elseif ($product->availability_status->value === 'out_of_stock')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Agotado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Bajo pedido
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </article>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                        <p class="mt-3 text-base font-semibold text-slate-800">No hay productos en esta sección</p>
                        <p class="mt-1 text-sm text-slate-500">Prueba seleccionando otra categoría o vuelve a ver todo el catálogo.</p>
                        @if ($selectedCategory)
                            <a class="mt-4 inline-block rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" href="{{ route('shops.show', $shop) }}">
                                Ver todos los productos
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if ($products->hasPages())
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @endif

            <x-ad-slot position="catalog_between_rows" />

            <!-- Value proposition footer banner -->
            <section class="mt-12 rounded-xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <div class="flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">¿Tienes un negocio y vendes por WhatsApp?</h3>
                        <p class="mt-1 text-xs text-slate-500">Crea tu propio catálogo profesional en minutos. Sin comisiones por venta.</p>
                    </div>
                    <a class="shrink-0 rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-blue-700" href="{{ url('/register') }}">
                        Crear mi catálogo gratis
                    </a>
                </div>
            </section>
        </main>

        <footer class="mt-12 border-t border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-500 sm:px-8">
            <p>
                Vitrina digital impulsada por <strong class="text-slate-700">MiCatalogo</strong>. Contacto directo por WhatsApp; no procesamos pagos, órdenes ni entregas.
            </p>
            <p class="mt-2">
                Created by
                <a class="font-semibold text-slate-700 underline decoration-blue-400 underline-offset-4 hover:text-blue-700" href="https://bsolutions.dev" target="_blank" rel="noopener noreferrer">
                    BSolutions.dev
                </a>
            </p>
        </footer>
    </div>

    <!-- Share Script -->
    <script>
        function shareStore(name, url) {
            if (navigator.share) {
                navigator.share({
                    title: name + ' en MiCatalogo',
                    text: 'Mira el catálogo de productos de ' + name,
                    url: url,
                }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Enlace del catálogo copiado al portapapeles');
                });
            }
        }
    </script>
</x-layouts.app>
