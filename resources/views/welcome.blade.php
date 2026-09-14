<x-layouts.app title="MiCatalogo | Productos y tiendas">
    <div class="min-h-screen bg-[#F5F7FA] text-slate-800">
        <!-- Main Topbar with Dominant Search -->
        <header class="bg-[#0F172A] text-white">
            <div class="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-3 px-4 py-3 lg:flex-nowrap sm:px-8">
                <a class="shrink-0 text-xl font-bold tracking-tight" href="{{ route('home') }}">
                    Mi<span class="text-blue-400">Catalogo</span>
                </a>
                <form class="order-3 flex w-full flex-1 lg:order-none" method="GET" action="{{ route('home') }}">
                    @if (request('shipping'))
                        <input type="hidden" name="shipping" value="{{ request('shipping') }}">
                    @endif
                    @if (request('stock'))
                        <input type="hidden" name="stock" value="{{ request('stock') }}">
                    @endif
                    @if (request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if (request('tienda'))
                        <input type="hidden" name="tienda" value="{{ request('tienda') }}">
                    @endif
                    @if (request('min_price'))
                        <input type="hidden" name="min_price" value="{{ request('min_price') }}">
                    @endif
                    @if (request('max_price'))
                        <input type="hidden" name="max_price" value="{{ request('max_price') }}">
                    @endif

                    <!-- Category Selector (Amazon Style) -->
                    <div class="relative hidden sm:flex items-center">
                        <label for="search-category" class="sr-only">Categoría</label>
                        <select id="search-category" name="categoria" class="h-full rounded-l-md border-r border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 outline-none hover:bg-slate-200 transition cursor-pointer max-w-[150px] truncate">
                            <option value="">Todas</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->slug }}" @selected(request('categoria') === $cat->slug)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <input class="min-w-0 flex-1 rounded-l-md sm:rounded-l-none border-0 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none placeholder:text-slate-400" name="q" value="{{ $searchQuery }}" placeholder="¿Qué estás buscando? (producto o tienda)" type="search">
                    <button class="rounded-r-md bg-blue-600 px-5 font-semibold text-white hover:bg-blue-700 transition flex items-center justify-center cursor-pointer shadow-xs" type="submit">
                        <svg class="h-4 w-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span class="hidden sm:inline">Buscar</span>
                    </button>
                </form>
                <nav class="ml-auto flex shrink-0 items-center gap-3 text-sm">
                    @auth
                        <!-- Amazon-Style Account Menu -->
                        <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="flex items-center gap-2 rounded-md border border-transparent px-3 py-1.5 text-left text-xs leading-tight text-white hover:border-slate-700 hover:bg-slate-800/80 transition focus:outline-none cursor-pointer"
                                aria-expanded="false"
                                :aria-expanded="open.toString()"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white shadow-xs">
                                    {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[11px] text-slate-400">Hola, {{ str(auth()->user()->name)->explode(' ')->first() }}</span>
                                    <span class="flex items-center gap-1 font-bold text-white text-xs">
                                        Mi Cuenta y Catálogos
                                        <svg class="h-3 w-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </div>
                            </button>

                            <!-- Dropdown Menu -->
                            <div 
                                x-show="open" 
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                                class="absolute right-0 mt-1 w-72 origin-top-right rounded-xl border border-slate-200 bg-white p-2 text-slate-800 shadow-2xl ring-1 ring-black/5 z-50 divide-y divide-slate-100"
                            >
                                <!-- User Info Header -->
                                <div class="px-3 py-2.5">
                                    <p class="text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name }}</p>
                                    <p class="text-[11px] text-slate-500 truncate">{{ auth()->user()->email }}</p>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        @if (auth()->user()->isAdmin())
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                                                Administrador / Owner
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200">
                                                Vendedor
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Seller Management Links -->
                                <div class="py-1 text-xs">
                                    <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                        Tus Catálogos
                                    </div>
                                    <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition">
                                        <svg class="h-4 w-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-slate-800">Panel de tiendas</span>
                                            <span class="text-[10px] text-slate-500">Gestionar productos y catálogos</span>
                                        </div>
                                    </a>
                                    <a href="{{ route('seller.shops.create') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition">
                                        <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <span class="font-semibold text-slate-800">Crear nueva tienda</span>
                                    </a>
                                    @if ($primaryShop = auth()->user()->shops()->first())
                                        <a href="{{ route('seller.shops.metrics.index', $primaryShop) }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-indigo-700 hover:bg-indigo-50 transition">
                                            <svg class="h-4 w-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-slate-800">Métricas & Código QR</span>
                                                <span class="text-[10px] text-slate-500">{{ $primaryShop->name }}</span>
                                            </div>
                                        </a>
                                    @endif
                                </div>

                                <!-- Admin Management Links (if admin) -->
                                @if (auth()->user()->isAdmin())
                                    <div class="py-1 text-xs">
                                        <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-500">
                                            Panel Administrativo
                                        </div>
                                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition">
                                            <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            <span class="font-semibold text-slate-800">Dashboard del Sistema</span>
                                        </a>
                                        <a href="{{ route('admin.categories.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition">
                                            <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                            <span class="font-semibold text-slate-800">Categorías globales</span>
                                        </a>
                                        <a href="{{ route('admin.reports.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition">
                                            <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <span class="font-semibold text-slate-800">Cola de reportes</span>
                                        </a>
                                    </div>
                                @endif

                                <!-- Sign Out Link -->
                                <div class="py-1 text-xs">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-rose-600 hover:bg-rose-50 hover:text-rose-800 transition text-left font-semibold cursor-pointer">
                                            <svg class="h-4 w-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                            Cerrar sesión
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Direct quick link to seller panel -->
                        <a class="rounded-md bg-blue-600 px-3 py-2 font-semibold hover:bg-blue-700 text-white text-xs transition shadow-xs hidden sm:inline-block" href="{{ route('seller.dashboard') }}">
                            Mis catálogos
                        </a>
                    @else
                        <!-- Guest Links -->
                        <a class="hidden text-slate-200 hover:text-white sm:block" href="{{ route('login') }}">
                            Iniciar sesión
                        </a>
                        <a class="rounded-md bg-blue-600 px-3.5 py-2 font-semibold hover:bg-blue-700 text-white whitespace-nowrap shrink-0 text-xs sm:text-sm shadow-xs" href="{{ route('register') }}">
                            Crear mi catalogo gratis
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <!-- Categories Bar -->
        <nav class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-[1400px] items-center gap-2 overflow-x-auto px-4 py-2.5 text-sm font-medium text-slate-700 sm:px-8">
                <a class="whitespace-nowrap rounded-md px-2.5 py-1 transition {{ !request('categoria') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:text-blue-700' }}" href="{{ route('home', array_filter(['q' => $searchQuery, 'shipping' => $shipping])) }}">
                    Todas
                </a>
                @foreach ($categories as $category)
                    <a class="whitespace-nowrap rounded-md px-2.5 py-1 transition {{ request('categoria') === $category->slug ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:text-blue-700' }}" href="{{ route('home', array_filter(['q' => $searchQuery, 'categoria' => $category->slug, 'shipping' => $shipping])) }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </nav>

        <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-8">
            <!-- Informative Value Proposition Ribbon -->
            <section class="flex items-center gap-3 rounded-lg border border-blue-100 bg-blue-50 px-5 py-3.5 text-sm text-slate-700">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <p class="font-medium">
                    <strong class="text-blue-900">¿Vendes por WhatsApp?</strong> Publica tus productos una sola vez, comparte tu catálogo con un enlace directo y recibe pedidos sin comisiones.
                </p>
            </section>

            <!-- Product Showcase Section -->
            <section class="mt-8" id="productos">
                <!-- Section Header with Title & Sort -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-black text-slate-900 sm:text-2xl">
                            @if ($searchQuery)
                                Resultados para <span class="text-blue-600">"{{ $searchQuery }}"</span>
                            @elseif ($selectedCategory)
                                Catálogo: <span class="text-blue-600">{{ $selectedCategory->name }}</span>
                            @elseif ($shopSlug)
                                Tienda: <span class="text-blue-600">{{ $allShops->firstWhere('slug', $shopSlug)?->name ?? $shopSlug }}</span>
                            @else
                                Todos los Productos
                            @endif
                        </h1>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                            {{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}
                        </span>
                    </div>

                    <!-- Sort Selector Dropdown -->
                    <form method="GET" action="{{ route('home') }}" class="flex items-center gap-2 text-xs">
                        @foreach (request()->except(['sort', 'page']) as $k => $v)
                            @if (is_string($v) && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <label for="sort-select" class="font-semibold text-slate-500 shrink-0">Ordenar por:</label>
                        <select 
                            id="sort-select" 
                            name="sort" 
                            onchange="this.form.submit()" 
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs focus:border-blue-600 focus:outline-none cursor-pointer"
                        >
                            <option value="latest" @selected(empty($sort) || $sort === 'latest')>Relevancia / Recientes</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Precio: Menor a Mayor</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Precio: Mayor a Menor</option>
                            <option value="name_asc" @selected($sort === 'name_asc')>Nombre: A - Z</option>
                        </select>
                    </form>
                </div>

                <!-- Comprehensive Refinement Filter Toolbar -->
                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
                        <!-- Quick Filter Pills: Shipping, Stock, & Store -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mr-1">Filtros:</span>

                            <!-- Shipping Filter Pill -->
                            <a 
                                href="{{ request()->fullUrlWithQuery(['shipping' => $shipping === 'available' ? null : 'available', 'page' => null]) }}" 
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition border {{ $shipping === 'available' ? 'bg-blue-600 text-white border-blue-600 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:text-slate-900' }}"
                            >
                                <svg class="h-3.5 w-3.5 {{ $shipping === 'available' ? 'text-white' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h2m-8 0a2 2 0 100-4 2 2 0 000 4zm10 0a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                <span>Con envío</span>
                                @if ($shipping === 'available')
                                    <span class="text-[10px] opacity-80">✕</span>
                                @endif
                            </a>

                            <!-- In Stock Filter Pill -->
                            <a 
                                href="{{ request()->fullUrlWithQuery(['stock' => $stock === 'available' ? null : 'available', 'page' => null]) }}" 
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition border {{ $stock === 'available' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:text-slate-900' }}"
                            >
                                <span class="h-2 w-2 rounded-full {{ $stock === 'available' ? 'bg-white' : 'bg-emerald-500' }}"></span>
                                <span>En stock</span>
                                @if ($stock === 'available')
                                    <span class="text-[10px] opacity-80">✕</span>
                                @endif
                            </a>

                            <!-- Store Selector Dropdown -->
                            <form method="GET" action="{{ route('home') }}" class="inline-flex items-center">
                                @foreach (request()->except(['tienda', 'page']) as $k => $v)
                                    @if (is_string($v) && $v !== '')
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <select 
                                    name="tienda" 
                                    onchange="this.form.submit()" 
                                    class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 focus:border-blue-600 focus:outline-none cursor-pointer"
                                >
                                    <option value="">Todas las tiendas</option>
                                    @foreach ($allShops as $shopItem)
                                        <option value="{{ $shopItem->slug }}" @selected($shopSlug === $shopItem->slug)>
                                            {{ $shopItem->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>

                        <!-- Price Range Filters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px]">Precio:</span>
                            <a 
                                href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => 1000, 'page' => null]) }}" 
                                class="rounded-full px-2.5 py-1 text-xs font-medium border transition {{ $maxPrice == 1000 && empty($minPrice) ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                            >
                                &lt; RD$ 1K
                            </a>
                            <a 
                                href="{{ request()->fullUrlWithQuery(['min_price' => 1000, 'max_price' => 5000, 'page' => null]) }}" 
                                class="rounded-full px-2.5 py-1 text-xs font-medium border transition {{ $minPrice == 1000 && $maxPrice == 5000 ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                            >
                                RD$ 1K - 5K
                            </a>
                            <a 
                                href="{{ request()->fullUrlWithQuery(['min_price' => 5000, 'max_price' => null, 'page' => null]) }}" 
                                class="rounded-full px-2.5 py-1 text-xs font-medium border transition {{ $minPrice == 5000 && empty($maxPrice) ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                            >
                                &gt; RD$ 5K
                            </a>

                            <!-- Custom Price Range Inputs -->
                            <form method="GET" action="{{ route('home') }}" class="inline-flex items-center gap-1 ml-1">
                                @foreach (request()->except(['min_price', 'max_price', 'page']) as $k => $v)
                                    @if (is_string($v) && $v !== '')
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <input 
                                    type="number" 
                                    name="min_price" 
                                    value="{{ $minPrice }}" 
                                    placeholder="Min" 
                                    min="0" 
                                    class="w-14 rounded-md border border-slate-300 px-1.5 py-1 text-[11px] text-slate-800 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                                >
                                <span class="text-slate-300">-</span>
                                <input 
                                    type="number" 
                                    name="max_price" 
                                    value="{{ $maxPrice }}" 
                                    placeholder="Max" 
                                    min="0" 
                                    class="w-14 rounded-md border border-slate-300 px-1.5 py-1 text-[11px] text-slate-800 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                                >
                                <button type="submit" class="rounded-md bg-slate-800 px-2 py-1 text-[11px] font-bold text-white hover:bg-slate-700 transition cursor-pointer">
                                    RD$
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Active Filter Tags / Chips Banner -->
                @if ($hasActiveFilters)
                    <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-blue-50/60 p-2.5 border border-blue-100 text-xs">
                        <span class="font-bold text-blue-900 text-[11px]">Filtros aplicados:</span>

                        @if ($searchQuery)
                            <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Búsqueda: <strong>"{{ $searchQuery }}"</strong></span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        @if (request('categoria'))
                            <a href="{{ request()->fullUrlWithQuery(['categoria' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Categoría: <strong>{{ $selectedCategory?->name ?? request('categoria') }}</strong></span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        @if ($shipping === 'available')
                            <a href="{{ request()->fullUrlWithQuery(['shipping' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Con envío disponible</span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        @if ($stock === 'available')
                            <a href="{{ request()->fullUrlWithQuery(['stock' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Solo en stock</span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        @if ($shopSlug)
                            <a href="{{ request()->fullUrlWithQuery(['tienda' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Tienda: <strong>{{ $allShops->firstWhere('slug', $shopSlug)?->name ?? $shopSlug }}</strong></span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        @if ($minPrice || $maxPrice)
                            <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Precio: <strong>RD$ {{ $minPrice ? number_format((float)$minPrice) : '0' }} - {{ $maxPrice ? number_format((float)$maxPrice) : '∞' }}</strong></span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        @if ($sort && $sort !== 'latest')
                            <a href="{{ request()->fullUrlWithQuery(['sort' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition">
                                <span>Orden: <strong>{{ $sort === 'price_asc' ? 'Menor precio' : ($sort === 'price_desc' ? 'Mayor precio' : 'Nombre A-Z') }}</strong></span>
                                <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                            </a>
                        @endif

                        <a href="{{ route('home') }}" class="ml-auto inline-flex items-center gap-1 rounded-md bg-rose-600 px-2.5 py-1 text-white font-bold hover:bg-rose-700 transition shadow-2xs text-[11px]">
                            Limpiar todos ✕
                        </a>
                    </div>
                @endif

                <!-- Products Grid -->
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @forelse ($products as $product)
                        <article class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-xs transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                            <a class="block" href="{{ route('products.show', [$product->shop, $product]) }}">
                                <div class="relative aspect-square overflow-hidden bg-slate-100">
                                    @if ($product->images->isNotEmpty())
                                        <img src="{{ $product->images->first()->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover object-center transition duration-200 hover:scale-105" loading="lazy">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-slate-400">
                                            <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Zm5-12h.01" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <h2 class="line-clamp-2 min-h-10 text-sm font-semibold text-slate-800">{{ $product->name }}</h2>
                                    <p class="mt-2 text-lg font-bold tracking-tight text-slate-900">RD$ {{ number_format((float) $product->price, 0) }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $product->shop->name }}</p>
                                    <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800">
                                        <i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i>Disponible
                                    </span>
                                </div>
                            </a>
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500 shadow-2xs">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-800">No encontramos productos que coincidan</h3>
                            <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">
                                Intenta buscando con términos más amplios, ajustando los rangos de precio o eliminando algunos filtros aplicados.
                            </p>
                            <div class="mt-5">
                                <a class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition" href="{{ route('home') }}">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Restablecer todos los filtros
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>

                @if ($products->hasPages())
                    <div class="mt-6">
                        {{ $products->links() }}
                    </div>
                @endif

                <x-ad-slot position="home_after_grid_1" />
            </section>

            <!-- Featured Shops Section -->
            <section class="mt-10">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">Tiendas destacadas</h2>
                    <a class="text-sm font-semibold text-blue-700" href="#productos">Ver todas</a>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    @foreach ($shops as $shop)
                        <article class="flex items-center gap-3 rounded-md border border-slate-200 bg-white p-3 shadow-xs">
                            @if ($shop->logo_url)
                                <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover border border-slate-200">
                            @else
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-700">
                                    {{ str($shop->name)->substr(0, 1)->upper() }}
                                </div>
                            @endif
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-bold text-slate-800">{{ $shop->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Tienda independiente</p>
                                <a class="mt-1 inline-block text-xs font-semibold text-blue-700" href="{{ route('shops.show', $shop) }}">
                                    Ver catalogo →
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </main>

        <x-public-footer />
    </div>
</x-layouts.app>
