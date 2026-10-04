<x-layouts.app :title="($viewAll ?? false) ? 'Todas las tiendas | MiCatalogo' : 'Tus tiendas | MiCatalogo'">
    @php($isAssignedSellerOnly = auth()->user()?->isAssignedSellerOnly() ?? false)
    @php($canCreateShop = auth()->user()?->isAdmin() || ! (auth()->user()?->hasActiveShopAssignment() ?? false))
    @php($assignedShop = $isAssignedSellerOnly ? $shops->first() : null)
    <!-- Persistent Unified Navigation -->
    <x-admin.header />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            @if ($isAssignedSellerOnly && !($viewAll ?? false) && $assignedShop)
                <section class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-950 text-white shadow-lg">
                    <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_1.2fr] lg:items-center">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-200">
                                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                                Guía para vendedores
                            </div>
                            <h2 class="mt-4 max-w-lg text-2xl font-black tracking-tight sm:text-3xl">Vende en {{ $assignedShop->name }}.</h2>
                            <p class="mt-3 max-w-xl text-sm leading-6 text-emerald-100/80">Tu administrador ya preparó la tienda. Entra a vender, consulta el catálogo y registra cada cobro desde tu espacio de trabajo.</p>
                            <a class="mt-6 inline-flex items-center gap-2 rounded-lg bg-emerald-400 px-4 py-2.5 text-sm font-bold text-emerald-950 shadow-lg shadow-emerald-950/40 transition hover:bg-emerald-300" href="{{ route('seller.shops.inventory.index', $assignedShop) }}">
                                Ir a vender
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-white/10 bg-white/[0.07] p-4">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-400 text-sm font-black text-emerald-950">01</span>
                                <p class="mt-4 text-sm font-bold">Revisa productos</p>
                                <p class="mt-1 text-xs leading-5 text-emerald-100/70">Consulta precios y disponibilidad antes de ofrecer.</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/[0.07] p-4">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-cyan-400 text-sm font-black text-cyan-950">02</span>
                                <p class="mt-4 text-sm font-bold">Registra la venta</p>
                                <p class="mt-1 text-xs leading-5 text-emerald-100/70">Usa Vender para descontar existencias correctamente.</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/[0.07] p-4">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-300 text-sm font-black text-amber-950">03</span>
                                <p class="mt-4 text-sm font-bold">Confirma el cobro</p>
                                <p class="mt-1 text-xs leading-5 text-emerald-100/70">Verifica el total y entrega el comprobante al cliente.</p>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            @if (! $isAssignedSellerOnly && !($viewAll ?? false) && $shops->isEmpty())
                <section
                    class="mb-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 text-white shadow-lg"
                    x-data="{
                        step: 1,
                        timer: null,
                        start() {
                            this.timer = setInterval(() => { this.step = this.step === 4 ? 1 : this.step + 1 }, 4500);
                        },
                        stop() {
                            clearInterval(this.timer);
                            this.timer = null;
                        },
                        goTo(step) {
                            this.step = step;
                            this.stop();
                            this.start();
                        }
                    }"
                    x-init="start()"
                    @mouseenter="stop()"
                    @mouseleave="start()"
                >
                    <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_1.05fr] lg:items-center lg:p-10">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-400/30 bg-blue-400/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-blue-200">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-blue-300"></span>
                                Guía para administradores de tienda
                            </div>
                            <h2 class="mt-4 max-w-lg text-2xl font-black tracking-tight sm:text-3xl">Tu vitrina comienza con una tienda.</h2>
                            <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300">En pocos minutos tendrás un enlace para mostrar tus productos y recibir pedidos por WhatsApp.</p>
                            <a class="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-950/40 transition hover:bg-blue-400" href="{{ route('seller.shops.create') }}">
                                Crear mi tienda
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 sm:p-5">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <p class="text-xs font-bold text-slate-300">Así empiezas</p>
                                <p class="text-xs font-semibold text-blue-200"><span x-text="step"></span> de 4</p>
                            </div>

                            <div class="min-h-44">
                                <div x-show="step === 1" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-3 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
                                    <div class="flex items-start gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-500 text-lg font-black">01</span>
                                        <div>
                                            <p class="text-lg font-bold">Crea tu tienda</p>
                                            <p class="mt-1 text-sm leading-6 text-slate-300">Ponle nombre a tu negocio y define el enlace que compartirás con tus clientes.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 rounded-xl border border-white/10 bg-slate-900/70 p-3 text-xs text-slate-400">
                                        <span class="mb-2 block h-2 w-24 rounded bg-slate-700"></span>
                                        <span class="block h-8 rounded border border-blue-400/40 bg-blue-400/10"></span>
                                    </div>
                                </div>

                                <div x-show="step === 2" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-3 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
                                    <div class="flex items-start gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-lg font-black">02</span>
                                        <div>
                                            <p class="text-lg font-bold">Conecta tu WhatsApp</p>
                                            <p class="mt-1 text-sm leading-6 text-slate-300">Agrega el número donde quieres recibir las consultas y pedidos de tus clientes.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex items-center gap-3 rounded-xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-xs text-emerald-100">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-400/20 text-base">W</span>
                                        WhatsApp directo a tu negocio
                                    </div>
                                </div>

                                <div x-show="step === 3" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-3 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
                                    <div class="flex items-start gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-lg font-black">03</span>
                                        <div>
                                            <p class="text-lg font-bold">Sube tus productos</p>
                                            <p class="mt-1 text-sm leading-6 text-slate-300">Añade fotos, precios y disponibilidad para que tu catálogo esté listo para vender.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex gap-2 rounded-xl border border-amber-300/20 bg-amber-400/10 p-3">
                                        <span class="h-12 w-12 rounded-lg bg-white/15"></span>
                                        <span class="h-12 w-12 rounded-lg bg-white/10"></span>
                                        <span class="h-12 w-12 rounded-lg bg-white/5"></span>
                                    </div>
                                </div>

                                <div x-show="step === 4" x-cloak x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-3 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
                                    <div class="flex items-start gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-500 text-lg font-black">04</span>
                                        <div>
                                            <p class="text-lg font-bold">Comparte tu vitrina</p>
                                            <p class="mt-1 text-sm leading-6 text-slate-300">Copia tu enlace y envíalo por WhatsApp, Instagram o donde tus clientes ya te conocen.</p>
                                        </div>
                                    </div>
                                    <div class="mt-6 rounded-xl border border-violet-300/20 bg-violet-400/10 px-3 py-3 text-xs text-violet-100">micatalogo.bsolutions.dev/tienda/tu-negocio</div>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-4 gap-2" aria-label="Pasos de la guía">
                                <button class="h-1.5 rounded-full transition" :class="step === 1 ? 'bg-blue-400' : 'bg-white/15'" type="button" aria-label="Paso 1" @click="goTo(1)"></button>
                                <button class="h-1.5 rounded-full transition" :class="step === 2 ? 'bg-emerald-400' : 'bg-white/15'" type="button" aria-label="Paso 2" @click="goTo(2)"></button>
                                <button class="h-1.5 rounded-full transition" :class="step === 3 ? 'bg-amber-400' : 'bg-white/15'" type="button" aria-label="Paso 3" @click="goTo(3)"></button>
                                <button class="h-1.5 rounded-full transition" :class="step === 4 ? 'bg-violet-400' : 'bg-white/15'" type="button" aria-label="Paso 4" @click="goTo(4)"></button>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

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
                                {{ ($viewAll ?? false) ? 'Todas las tiendas del sistema' : ($isAssignedSellerOnly ? 'Tienda asignada' : 'Tus tiendas') }}
                            </h1>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600 border border-slate-200">
                                {{ $shops->total() }} {{ $shops->total() === 1 ? 'tienda' : 'tiendas' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                             {{ ($viewAll ?? false) ? 'Vista de administración: inspecciona, filtra y gestiona cualquier vitrina de la plataforma.' : ($isAssignedSellerOnly ? 'Tienes acceso operativo a esta tienda; la administración pertenece al propietario.' : 'Configura tu vitrina y el WhatsApp donde recibirás consultas directas de clientes.') }}
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

                         @if (! $isAssignedSellerOnly && $canCreateShop && ($shops->isEmpty() || !($viewAll ?? false)))
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
                        <div class="relative min-w-0 w-full flex-1 sm:min-w-64">
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
                    <?php foreach ($shops as $shop): ?>
                        <?php
                            $visibleMenus = app(\App\Services\SellerMenuService::class)->forUser($shop, auth()->user());
                            $canSeeMenu = fn (string $key): bool => in_array($key, $visibleMenus, true);
                            $canManageShop = app(\App\Services\SellerMenuService::class)->canManage($shop, auth()->user());
                        ?>
                        <article class="grid gap-5 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-xs xl:grid-cols-2 xl:items-start">
                            <div class="flex min-w-0 items-start gap-3.5">
                                @if ($shop->logo_url)
                                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-10 w-10 shrink-0 rounded-xl object-cover border border-slate-200 shadow-2xs">
                                @else
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 font-black text-blue-700 text-sm">
                                        {{ strtoupper(substr($shop->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1 space-y-1">
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
                                        <span class="break-all rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600 font-mono border border-slate-200" title="Vendedor responsable">
                                            👤 {{ $shop->user->name }} ({{ $shop->user->email }})
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span class="break-all font-mono text-slate-600">/tienda/{{ $shop->slug }}</span>
                                    <span>·</span>
                                    <a class="break-all font-medium transition hover:text-emerald-700" href="https://wa.me/{{ $shop->whatsapp_country_code }}{{ $shop->whatsapp_number }}" target="_blank">
                                        WhatsApp: +{{ $shop->whatsapp_country_code }} {{ $shop->whatsapp_number }}
                                    </a>
                                </div>

                                @if (($viewAll ?? false) && auth()->user()?->isAdmin())
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-800">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12z"/></svg>
                                            {{ number_format((float) ($shop->storage_mb ?? 0), 2) }} MB usados
                                        </span>
                                        <span class="text-slate-400">{{ number_format((int) ($shop->storage_image_count ?? 0)) }} imágenes</span>
                                    </div>
                                @endif
                            </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex min-w-0 w-full flex-wrap items-center gap-2 text-xs xl:justify-end">
                                @if ($canSeeMenu('products'))
                                <a 
                                    class="whitespace-nowrap rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 font-bold text-blue-700 shadow-2xs transition hover:bg-blue-100"
                                    href="{{ route('shops.show', $shop) }}" 
                                    target="_blank"
                                    title="Ver catálogo público"
                                >
                                    Ver vitrina ↗
                                </a>

                                <a 
                                    class="whitespace-nowrap rounded-lg bg-blue-600 px-3 py-1.5 font-bold text-white shadow-2xs transition hover:bg-blue-700"
                                    href="{{ route('seller.shops.products.index', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Productos ({{ $shop->products_count ?? $shop->products()->count() }})
                                </a>
                                @endif

                                @if ($canSeeMenu('sales'))
                                <a
                                    class="whitespace-nowrap rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 font-semibold text-amber-800 shadow-2xs transition hover:bg-amber-100"
                                    href="{{ route('seller.shops.inventory.index', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Vender
                                </a>
                                @endif

                                @if ($canSeeMenu('products') && $canManageShop)
                                <a 
                                    class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                                    href="{{ route('seller.shops.products.bulk.create', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Subida masiva
                                </a>
                                @endif

                                @if ($canSeeMenu('products'))
                                <a 
                                    class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                                    href="{{ route('seller.shops.categories.index', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Categorías
                                </a>
                                @endif

                                @if ($canSeeMenu('metrics'))
                                <a 
                                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-bold text-indigo-700 shadow-2xs transition hover:bg-indigo-100"
                                    href="{{ route('seller.shops.metrics.index', $shop) }}"
                                    wire:navigate.hover
                                    title="Ver métricas de visitas, contactos WhatsApp y código QR"
                                >
                                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    <span>Métricas & QR</span>
                                </a>
                                @endif

                                @if ($canSeeMenu('shop_settings') && $canManageShop)
                                <a 
                                    class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                                    href="{{ route('seller.shops.edit', $shop) }}"
                                    wire:navigate.hover
                                >
                                    Editar tienda
                                </a>
                                @endif

                                @if (($viewAll ?? false) && auth()->user()?->isAdmin())
                                    <form method="POST" action="{{ route('admin.shops.toggle-status', $shop) }}" class="inline">
                                        @csrf
                                        <button 
                                            type="submit" 
                                            class="whitespace-nowrap rounded-lg border px-3 py-1.5 font-bold transition shadow-2xs cursor-pointer {{ $shop->status === 'active' ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"
                                        >
                                            {{ $shop->status === 'active' ? 'Suspender' : 'Activar' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    <?php endforeach; ?>
                    @if ($shops->isEmpty())
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
                             @elseif ($isAssignedSellerOnly)
                                 <h3 class="mt-3 text-sm font-bold text-slate-800">No tienes una tienda propia</h3>
                                 <p class="mt-1 text-xs text-slate-500">Tu cuenta tiene acceso para vender en una tienda asignada.</p>
                             @else
                                 <h3 class="mt-3 text-sm font-bold text-slate-800">Aún no tienes una tienda</h3>
                                 <p class="mt-1 text-xs text-slate-500">Crea tu primera vitrina para comenzar a publicar productos.</p>
                                 @if ($canCreateShop)
                                     <div class="mt-4">
                                     <a href="{{ route('seller.shops.create') }}" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shadow-2xs">
                                         + Crear tienda
                                     </a>
                                     </div>
                                 @endif
                             @endif
                        </div>
                    @endif
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
