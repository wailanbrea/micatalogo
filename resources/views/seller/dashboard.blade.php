<x-layouts.app :title="($viewAll ?? false) ? 'Todas las tiendas | MiCatalogo' : 'Tus tiendas | MiCatalogo'">
    <!-- Persistent Unified Navigation -->
    <x-admin.header />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <!-- Header Card -->
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                @if (session('status'))
                    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-xs font-bold text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-black text-slate-900">
                                {{ ($viewAll ?? false) ? 'Todas las tiendas del sistema' : 'Tus tiendas' }}
                            </h1>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600 border border-slate-200">
                                {{ $shops->total() }} {{ $shops->total() === 1 ? 'tienda' : 'tiendas' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ ($viewAll ?? false) ? 'Vista de administración: inspecciona, filtra y gestiona cualquier vitrina de la plataforma.' : 'Configura tu vitrina y el WhatsApp donde recibirás consultas directas de clientes.' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @if (auth()->user()?->isAdmin())
                            <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-xs font-semibold">
                                <a 
                                    class="rounded-md px-3 py-1.5 transition {{ !($viewAll ?? false) ? 'bg-white text-blue-700 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900' }}" 
                                    href="{{ route('seller.dashboard') }}"
                                >
                                    Mis tiendas ({{ $ownedCount ?? 1 }})
                                </a>
                                <a 
                                    class="rounded-md px-3 py-1.5 transition {{ ($viewAll ?? false) ? 'bg-white text-blue-700 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900' }}" 
                                    href="{{ route('seller.dashboard', ['view' => 'all']) }}"
                                >
                                    Todas ({{ $totalCount ?? 4 }})
                                </a>
                            </div>
                        @endif

                        @if ($shops->isEmpty() || !($viewAll ?? false))
                            <a class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shadow-xs cursor-pointer" href="{{ route('seller.shops.create') }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Crear tienda</span>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <form method="GET" action="{{ route('seller.dashboard') }}" class="mt-5 space-y-3">
                    @if ($viewAll ?? false)
                        <input type="hidden" name="view" value="all">
                    @endif

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Text Search Input -->
                        <div class="relative min-w-64 flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input 
                                type="search" 
                                name="q" 
                                value="{{ $search ?? '' }}" 
                                placeholder="Buscar por nombre, slug, WhatsApp o correo del vendedor..." 
                                class="w-full rounded-lg border border-slate-300 pl-9 pr-4 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 transition"
                            >
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <select 
                                name="status" 
                                class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-xs text-slate-700 font-medium focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 cursor-pointer"
                                onchange="this.form.submit()"
                            >
                                <option value="">Todos los estados</option>
                                <option value="active" @selected(($status ?? '') === 'active')>Activas</option>
                                <option value="suspended" @selected(($status ?? '') === 'suspended')>Suspendidas</option>
                            </select>
                        </div>

                        <!-- Shipping Filter -->
                        <div>
                            <select 
                                name="shipping" 
                                class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-xs text-slate-700 font-medium focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 cursor-pointer"
                                onchange="this.form.submit()"
                            >
                                <option value="">Envío: Todos</option>
                                <option value="yes" @selected(($shipping ?? '') === 'yes')>Con envío</option>
                                <option value="no" @selected(($shipping ?? '') === 'no')>Sin envío</option>
                            </select>
                        </div>

                        <!-- Sort Dropdown -->
                        <div>
                            <select 
                                name="sort" 
                                class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-xs text-slate-700 font-medium focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 cursor-pointer"
                                onchange="this.form.submit()"
                            >
                                <option value="latest" @selected(($sort ?? '') === 'latest')>Más recientes</option>
                                <option value="oldest" @selected(($sort ?? '') === 'oldest')>Más antiguas</option>
                                <option value="name_asc" @selected(($sort ?? '') === 'name_asc')>Nombre (A - Z)</option>
                                <option value="name_desc" @selected(($sort ?? '') === 'name_desc')>Nombre (Z - A)</option>
                                <option value="products_desc" @selected(($sort ?? '') === 'products_desc')>Más productos</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer shadow-2xs"
                        >
                            <span>Filtrar</span>
                        </button>
                    </div>

                    <!-- Active Filter Chips Banner -->
                    @if ($hasActiveFilters ?? false)
                        <div class="flex flex-wrap items-center gap-2 rounded-lg bg-blue-50/70 p-2.5 border border-blue-100 text-xs">
                            <span class="font-bold text-blue-900 text-[11px]">Filtros aplicados:</span>

                            @if (!empty($search))
                                <a 
                                    href="{{ request()->fullUrlWithQuery(['q' => null]) }}" 
                                    class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition"
                                >
                                    <span>Búsqueda: <strong>"{{ $search }}"</strong></span>
                                    <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                                </a>
                            @endif

                            @if (!empty($status))
                                <a 
                                    href="{{ request()->fullUrlWithQuery(['status' => null]) }}" 
                                    class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition"
                                >
                                    <span>Estado: <strong>{{ $status === 'active' ? 'Activas' : 'Suspendidas' }}</strong></span>
                                    <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                                </a>
                            @endif

                            @if (!empty($shipping))
                                <a 
                                    href="{{ request()->fullUrlWithQuery(['shipping' => null]) }}" 
                                    class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition"
                                >
                                    <span>Envío: <strong>{{ $shipping === 'yes' ? 'Con envío' : 'Sin envío' }}</strong></span>
                                    <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                                </a>
                            @endif

                            @if (!empty($sort) && $sort !== 'latest')
                                <a 
                                    href="{{ request()->fullUrlWithQuery(['sort' => null]) }}" 
                                    class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 text-slate-700 font-medium border border-blue-200 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition"
                                >
                                    <span>Orden: <strong>{{ $sort }}</strong></span>
                                    <span class="font-bold text-slate-400 hover:text-rose-600">✕</span>
                                </a>
                            @endif

                            <a 
                                href="{{ route('seller.dashboard', ($viewAll ?? false) ? ['view' => 'all'] : []) }}" 
                                class="ml-auto inline-flex items-center gap-1 rounded-md bg-rose-600 px-2.5 py-1 text-white font-bold hover:bg-rose-700 transition shadow-2xs text-[11px]"
                            >
                                Limpiar filtros ✕
                            </a>
                        </div>
                    @endif
                </form>

                <!-- Store Cards List -->
                <div class="mt-6 space-y-3">
                    @forelse ($shops as $shop)
                        <article class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-xs">
                            <div class="flex items-center gap-3.5">
                                @if ($shop->logo_url)
                                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-10 w-10 shrink-0 rounded-xl object-cover border border-slate-200 shadow-2xs">
                                @else
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 font-black text-blue-700 text-sm">
                                        {{ strtoupper(substr($shop->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-bold text-slate-900">{{ $shop->name }}</h2>
                                    
                                    <!-- Status Pill -->
                                    @if ($shop->status === 'active')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Activa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Suspendida
                                        </span>
                                    @endif

                                    <!-- Shipping Pill -->
                                    @if ($shop->offers_shipping)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-200">
                                            🚚 Con envío
                                        </span>
                                    @endif

                                    <!-- Owner Email (in View All mode) -->
                                    @if (($viewAll ?? false) && $shop->user)
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600 font-mono border border-slate-200" title="Vendedor responsable">
                                            👤 {{ $shop->user->name }} ({{ $shop->user->email }})
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span class="font-mono text-slate-600">/tienda/{{ $shop->slug }}</span>
                                    <span>·</span>
                                    <a class="hover:text-emerald-700 transition font-medium" href="https://wa.me/{{ $shop->whatsapp_country_code }}{{ $shop->whatsapp_number }}" target="_blank">
                                        WhatsApp: +{{ $shop->whatsapp_country_code }} {{ $shop->whatsapp_number }}
                                    </a>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap items-center gap-2 text-xs sm:shrink-0">
                                <a 
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 font-bold text-blue-700 hover:bg-blue-100 transition shadow-2xs" 
                                    href="{{ route('shops.show', $shop) }}" 
                                    target="_blank"
                                    title="Ver catálogo público"
                                >
                                    Ver vitrina ↗
                                </a>

                                <a 
                                    class="rounded-lg bg-blue-600 px-3 py-1.5 font-bold text-white shadow-2xs hover:bg-blue-700 transition" 
                                    href="{{ route('seller.shops.products.index', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Productos ({{ $shop->products_count ?? $shop->products()->count() }})
                                </a>

                                <a
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 font-semibold text-amber-800 hover:bg-amber-100 transition shadow-2xs"
                                    href="{{ route('seller.shops.inventory.index', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Inventario
                                </a>

                                <a 
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs" 
                                    href="{{ route('seller.shops.products.bulk.create', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Subida masiva
                                </a>

                                <a 
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs" 
                                    href="{{ route('seller.shops.categories.index', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Categorías
                                </a>

                                <a 
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-bold text-indigo-700 hover:bg-indigo-100 transition shadow-2xs inline-flex items-center gap-1.5" 
                                    href="{{ route('seller.shops.metrics.index', $shop) }}"
                                    wire:navigate.hover
                                    title="Ver métricas de visitas, contactos WhatsApp y código QR"
                                >
                                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    <span>Métricas & QR</span>
                                </a>

                                <a 
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs" 
                                    href="{{ route('seller.shops.edit', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Configuración
                                </a>

                                @if (($viewAll ?? false) && auth()->user()?->isAdmin())
                                    <form method="POST" action="{{ route('admin.shops.toggle-status', $shop) }}" class="inline">
                                        @csrf
                                        <button 
                                            type="submit" 
                                            class="rounded-lg border px-3 py-1.5 font-bold transition shadow-2xs cursor-pointer {{ $shop->status === 'active' ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"
                                        >
                                            {{ $shop->status === 'active' ? 'Suspender' : 'Activar' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center bg-slate-50/50">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            @if ($hasActiveFilters ?? false)
                                <h3 class="mt-3 text-sm font-bold text-slate-800">No se encontraron tiendas</h3>
                                <p class="mt-1 text-xs text-slate-500">Ninguna tienda coincide con los filtros de búsqueda aplicados.</p>
                                <div class="mt-4">
                                    <a href="{{ route('seller.dashboard', ($viewAll ?? false) ? ['view' => 'all'] : []) }}" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shadow-2xs">
                                        Restablecer filtros
                                    </a>
                                </div>
                            @else
                                <h3 class="mt-3 text-sm font-bold text-slate-800">Aún no tienes una tienda</h3>
                                <p class="mt-1 text-xs text-slate-500">Crea tu primera vitrina para comenzar a publicar productos.</p>
                                <div class="mt-4">
                                    <a href="{{ route('seller.shops.create') }}" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shadow-2xs">
                                        + Crear tienda
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                @if ($shops->hasPages())
                    <div class="mt-6 border-t border-slate-100 pt-4">
                        {{ $shops->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
