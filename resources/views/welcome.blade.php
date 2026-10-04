<x-layouts.app title="MiCatalogo | Catálogo, ventas e inventario para tu negocio" description="Crea tu catálogo, controla inventario, registra ventas, genera comprobantes y administra tu negocio desde MiCatalogo. Empieza gratis.">
    <div class="min-h-screen bg-[#F8FAFC] text-slate-900 font-sans relative overflow-x-hidden selection:bg-blue-600 selection:text-white">
        <!-- Ambient background soft luminous lighting (Light / White theme) -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-[600px] w-[600px] rounded-full bg-blue-100/70 blur-[130px]"></div>
        <div class="pointer-events-none absolute top-[750px] -right-40 h-[700px] w-[700px] rounded-full bg-indigo-100/60 blur-[150px]"></div>
        <div class="pointer-events-none absolute top-[2100px] -left-40 h-[600px] w-[600px] rounded-full bg-emerald-100/50 blur-[140px]"></div>
        <div class="pointer-events-none absolute top-[3500px] -right-40 h-[700px] w-[700px] rounded-full bg-blue-100/60 blur-[160px]"></div>

        <!-- Sticky Header -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-xl border-b border-slate-200/80 shadow-xs transition-all">
            <div class="h-20 max-w-7xl mx-auto min-w-0 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-2 sm:gap-4">
                <!-- Logo -->
                <a class="flex items-center gap-2.5 group text-decoration-none" href="{{ route('home') }}">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20 group-hover:bg-blue-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <span class="text-lg sm:text-xl font-extrabold tracking-tight text-slate-900">
                        Mi<span class="text-blue-600">Catalogo</span>
                    </span>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-7">
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#ejemplos">Ejemplos</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#como-funciona">Cómo funciona</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#ventajas">Ventajas</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="{{ route('downloads.index') }}">Descarga</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#preguntas-frecuentes">Preguntas frecuentes</a>
                </nav>

                <!-- Auth / Guest Actions -->
                <div class="flex shrink-0 items-center gap-1 sm:gap-3">
                    <a class="inline-flex items-center justify-center rounded-lg px-2 py-1.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 sm:px-3 sm:text-sm" href="{{ route('support.create') }}">Soporte</a>
                    @auth
                        <!-- Authenticated Dropdown Menu -->
                        <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-left text-xs text-slate-700 hover:border-slate-300 hover:bg-slate-50 transition shadow-xs cursor-pointer"
                                aria-expanded="false"
                                :aria-expanded="open.toString()"
                            >
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-xs font-bold text-white shadow-xs">
                                    {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                </div>
                                <div class="hidden sm:flex flex-col">
                                    <span class="text-[11px] text-slate-500">Hola, {{ str(auth()->user()->name)->explode(' ')->first() }}</span>
                                    <span class="flex items-center gap-1 font-bold text-slate-900 text-xs">
                                        Mi Cuenta y Catálogos
                                        <svg class="h-3 w-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </div>
                            </button>

                            <!-- Dropdown Content -->
                            <div 
                                x-show="open" 
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                                class="absolute right-0 mt-2 w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-2 text-slate-800 shadow-xl z-50 divide-y divide-slate-100"
                            >
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

                                <div class="py-1 text-xs">
                                    <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition">
                                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        <span class="font-medium">Panel de tiendas</span>
                                    </a>
                                    <a href="{{ route('seller.shops.create') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition">
                                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <span class="font-medium">Crear nueva tienda</span>
                                    </a>
                                    @if (auth()->user()->isAdmin())
                                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-rose-700 hover:bg-rose-50 transition">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            <span class="font-medium">Dashboard del Sistema</span>
                                        </a>
                                        <a href="{{ route('admin.categories.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50 transition">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                            <span class="font-medium">Categorías globales</span>
                                        </a>
                                    @endif
                                </div>

                                <div class="py-1 text-xs">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left flex items-center gap-2 rounded-lg px-3 py-2 text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                            <span class="font-medium">Cerrar sesión</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <a class="hidden sm:inline-flex text-sm font-semibold text-slate-700 hover:text-blue-600 px-3 py-1.5 transition-colors" href="{{ route('login') }}">
                            Iniciar sesión
                        </a>
                        <a class="inline-flex items-center justify-center bg-blue-600 text-white text-xs sm:text-sm font-semibold px-3 sm:px-4 py-2 rounded-xl shadow-md shadow-blue-500/20 hover:bg-blue-700 transition-all active:scale-98 whitespace-nowrap" href="{{ route('register') }}">
                            <span class="sm:hidden">Crear catálogo</span>
                            <span class="hidden sm:inline">Crear catálogo gratis</span>
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="w-full pt-20">
            <!-- 1. HERO SECTION -->
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
                    <!-- Left: Copy & CTAs -->
                    <div class="lg:col-span-7 flex flex-col items-start gap-5 text-left">
                        <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 border border-blue-100 px-3.5 py-1.5 rounded-full text-xs font-semibold shadow-2xs">
                            <span class="flex h-2 w-2 rounded-full bg-blue-600 animate-pulse"></span>
                            <span>Para vendedores independientes y tiendas por WhatsApp</span>
                        </div>

                        <h1 class="text-4xl sm:text-5xl lg:text-[56px] font-extrabold text-slate-900 tracking-tight leading-[1.12]">
                            Tu negocio empieza gratis.<br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">
                                Catálogo, ventas e inventario.
                            </span><br>
                            Mantén el control desde un solo lugar.
                        </h1>

                        <p class="text-lg text-slate-600 max-w-xl leading-relaxed">
                            Administra productos, ventas, inventario y comprobantes desde un solo lugar. Tus clientes ven únicamente tu catálogo y te contactan directamente por WhatsApp. Empieza gratis, sin tarjeta.
                        </p>

                        <!-- CTA Cluster -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full pt-2">
                            @guest
                                <a class="inline-flex items-center justify-center gap-2 bg-blue-600 text-white font-bold text-base px-7 py-3.5 rounded-xl shadow-lg shadow-blue-600/25 hover:bg-blue-700 transition-all active:scale-98 group" href="{{ route('register') }}">
                                    <span>Empezar gratis</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                                <a class="inline-flex items-center justify-center gap-2 bg-white text-slate-700 border border-slate-200 font-semibold text-base px-6 py-3.5 rounded-xl shadow-xs hover:bg-slate-50 hover:border-slate-300 transition-colors" href="#como-funciona">
                                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Ver cómo funciona</span>
                                </a>
                            @else
                                <a class="inline-flex items-center justify-center gap-2 bg-blue-600 text-white font-bold text-base px-7 py-3.5 rounded-xl shadow-lg shadow-blue-600/25 hover:bg-blue-700 transition-all active:scale-98 group" href="{{ route('seller.dashboard') }}">
                                    <span>Ir a mi panel de tiendas</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                                <a class="inline-flex items-center justify-center gap-2 bg-white text-slate-700 border border-slate-200 font-semibold text-base px-6 py-3.5 rounded-xl shadow-xs hover:bg-slate-50 hover:border-slate-300 transition-colors" href="{{ route('seller.shops.create') }}">
                                    <span>Crear nueva tienda</span>
                                </a>
                            @endguest
                        </div>

                        <!-- Microcopy Reassurance -->
                        <div class="flex flex-col gap-1 pt-1">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-600 text-xs font-semibold">
                                <span class="flex items-center gap-1.5 text-emerald-700">
                                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Gratis
                                </span>
                                <span class="flex items-center gap-1.5 text-emerald-700">
                                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Fácil de actualizar
                                </span>
                                <span class="flex items-center gap-1.5 text-emerald-700">
                                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Comparte un solo enlace
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">Gratis. Sin tarjeta de crédito. Sin complicaciones.</p>
                        </div>
                    </div>

                    <!-- Right: Interactive Smartphone Mockup -->
                    <div class="lg:col-span-5 flex justify-center lg:justify-end mt-4 lg:mt-0"
                         x-data="{ 
                             activeCat: 'todos', 
                             waNotification: false,
                             productCount: 24,
                             sendDemoWa(item) {
                                 this.waNotification = item;
                                 setTimeout(() => { this.waNotification = false; }, 3500);
                             }
                         }">
                        <div class="w-full max-w-[390px] bg-white rounded-[36px] p-4 shadow-2xl border-4 border-slate-100 ring-1 ring-slate-200/80 flex flex-col gap-3 relative transition-all">
                            <!-- Toast Alert on interactive WhatsApp Click -->
                            <div x-show="waNotification" 
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                                 class="absolute top-4 left-4 right-4 z-30 bg-emerald-600 text-white text-xs font-semibold p-3 rounded-2xl shadow-xl flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">💬</span>
                                    <span>¡Mensaje preparado en WhatsApp con el enlace de este producto!</span>
                                </div>
                                <button @click="waNotification = false" class="text-white/80 hover:text-white font-bold text-sm">✕</button>
                            </div>

                            <!-- Phone Status Bar -->
                            <div class="flex items-center justify-between px-2 text-slate-500 text-[11px] font-semibold">
                                <span>9:41</span>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.778 8.222c-4.296-4.296-11.26-4.296-15.556 0A1 1 0 01.808 6.808c5.076-5.077 13.308-5.077 18.384 0a1 1 0 01-1.414 1.414zM14.95 11.05a7 7 0 00-9.9 0 1 1 0 01-1.414-1.414 9 9 0 0112.728 0 1 1 0 01-1.414 1.414zm-2.829 2.828a3 3 0 00-4.242 0 1 1 0 01-1.415-1.414 5 5 0 017.072 0 1 1 0 01-1.415 1.414zM10 16a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                    <span class="w-5 h-2.5 rounded-xs border border-slate-500 flex items-center p-0.5"><span class="w-full h-full bg-slate-700 rounded-2xs"></span></span>
                                </div>
                            </div>

                            <!-- Mockup Store Header -->
                            <div class="bg-slate-50 p-3 rounded-2xl flex items-center justify-between border border-slate-200/80 shadow-xs">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-black text-xs shadow-xs tracking-wider">
                                        BS
                                    </div>
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1">
                                            <span class="font-bold text-xs text-slate-900">Bsolutions</span>
                                            <svg class="w-3.5 h-3.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </div>
                                        <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Abierto hoy
                                        </span>
                                    </div>
                                </div>
                                <button type="button" @click="sendDemoWa('tienda')" class="w-8 h-8 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center shadow-xs transition-colors cursor-pointer" title="Contactar tienda por WhatsApp">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                </button>
                            </div>

                            <!-- Mockup Internal Search -->
                            <div class="bg-slate-50 rounded-xl px-3 py-2 flex items-center gap-2 border border-slate-200 text-slate-400 text-xs">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <span class="text-[11px] text-slate-400">Buscar en Bsolutions...</span>
                            </div>

                            <!-- Category Filter Pills (Interactive) -->
                            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                                <button type="button" @click="activeCat = 'todos'" :class="activeCat === 'todos' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-full text-[11px] font-semibold transition-all whitespace-nowrap cursor-pointer">
                                    Todos (24)
                                </button>
                                <button type="button" @click="activeCat = 'tenis'" :class="activeCat === 'tenis' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-full text-[11px] font-semibold transition-all whitespace-nowrap cursor-pointer">
                                    Tenis (14)
                                </button>
                                <button type="button" @click="activeCat = 'camisas'" :class="activeCat === 'camisas' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-full text-[11px] font-semibold transition-all whitespace-nowrap cursor-pointer">
                                    Camisas (7)
                                </button>
                                <button type="button" @click="activeCat = 'gorras'" :class="activeCat === 'gorras' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-full text-[11px] font-semibold transition-all whitespace-nowrap cursor-pointer">
                                    Gorras (3)
                                </button>
                            </div>

                            <!-- Product Cards (Switch dynamically) -->
                            <div class="flex flex-col gap-2.5">
                                <!-- Card 1: Nike Air Max 270 -->
                                <div x-show="activeCat === 'todos' || activeCat === 'tenis'" 
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="bg-white rounded-2xl p-2.5 border border-slate-200/90 shadow-xs flex gap-3 items-center hover:border-blue-300 transition-colors">
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 shrink-0 overflow-hidden border border-slate-100 shadow-2xs">
                                        <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=300&h=300&q=80" alt="Nike Air Max 270" class="w-full h-full object-cover object-center" loading="lazy">
                                    </div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-900 truncate">Nike Air Max 270</span>
                                            <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-md shrink-0">● En stock</span>
                                        </div>
                                        <span class="text-sm font-black text-slate-900 mt-0.5 tabular-nums whitespace-nowrap">RD$ 4,500</span>
                                        <button type="button" @click="sendDemoWa('Nike Air Max 270')" class="mt-1.5 inline-flex items-center justify-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white py-1 px-2 rounded-lg text-[10px] font-bold shadow-xs w-full transition-all cursor-pointer">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            <span>Consultar por WhatsApp</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Card 2: Camisa Polo Slim Fit -->
                                <div x-show="activeCat === 'todos' || activeCat === 'camisas'"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="bg-white rounded-2xl p-2.5 border border-slate-200/90 shadow-xs flex gap-3 items-center hover:border-blue-300 transition-colors">
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 shrink-0 overflow-hidden border border-slate-100 shadow-2xs">
                                        <img src="https://images.unsplash.com/photo-1581655353564-df123a1eb820?auto=format&fit=crop&w=300&h=300&q=80" alt="Camisa Polo Slim Fit" class="w-full h-full object-cover object-center" loading="lazy">
                                    </div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-900 truncate">Camisa Polo Slim Fit</span>
                                            <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-md shrink-0">● En stock</span>
                                        </div>
                                        <span class="text-sm font-black text-slate-900 mt-0.5 tabular-nums whitespace-nowrap">RD$ 1,200</span>
                                        <button type="button" @click="sendDemoWa('Camisa Polo Slim Fit')" class="mt-1.5 inline-flex items-center justify-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white py-1 px-2 rounded-lg text-[10px] font-bold shadow-xs w-full transition-all cursor-pointer">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            <span>Consultar por WhatsApp</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Card 3: Gorra Urbana Street -->
                                <div x-show="activeCat === 'gorras'"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="bg-white rounded-2xl p-2.5 border border-slate-200/90 shadow-xs flex gap-3 items-center hover:border-blue-300 transition-colors">
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 shrink-0 overflow-hidden border border-slate-100 shadow-2xs">
                                        <img src="https://images.unsplash.com/photo-1588850561407-ed78c282e89b?auto=format&fit=crop&w=300&h=300&q=80" alt="Gorra Urbana Street" class="w-full h-full object-cover object-center" loading="lazy">
                                    </div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-900 truncate">Gorra Urbana Street</span>
                                            <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-md shrink-0">● En stock</span>
                                        </div>
                                        <span class="text-sm font-black text-slate-900 mt-0.5 tabular-nums whitespace-nowrap">RD$ 850</span>
                                        <button type="button" @click="sendDemoWa('Gorra Urbana Street')" class="mt-1.5 inline-flex items-center justify-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white py-1 px-2 rounded-lg text-[10px] font-bold shadow-xs w-full transition-all cursor-pointer">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            <span>Consultar por WhatsApp</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            
            <!-- ======================================================== -->
            <!-- 2. SECCIÓN ESTILO PUNTTO: "HECHO PARA LO QUE VENDES" (SMARTPHONE INTERACTIVO CON FOTOS REALES) -->
            <!-- ======================================================== -->
            <section class="w-full bg-slate-50/80 border-b border-slate-200/80 pt-20 pb-16 lg:py-24 scroll-mt-16" id="ejemplos"
                     x-data="{
                         activeRubro: 'perfumeria',
                         cartCount: 2,
                         waNotification: false,
                         filterOpen: true,
                         activeSort: 'recientes',
                         brandFilter: '',
                         itemsInCart: { 1: 1, 2: 1 },
                         rubros: {
                             perfumeria: {
                                 name: 'Perfumería',
                                 sub: 'Concentración · Tamaño · Género',
                                 storeName: 'Aroma Real RD',
                                 tag: 'Decants & Fragancias 100% Originales',
                                 avatar: 'AR',
                                 color: 'from-amber-600 to-rose-600',
                                 cats: ['Todo (26)', 'Decants (8)', 'Diseñador (12)', 'Árabes (6)'],
                                 brands: ['Lattafa', 'Valentino', 'Versace', 'Armaf'],
                                 products: [
                                     { id: 1, brand: 'LATTAFA', name: 'Yara Candy Eau de Parfum', detail: 'EDP · 100ml Original', price: '3,500', image: 'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Top Ventas' },
                                     { id: 2, brand: 'VALENTINO', name: 'Uomo Born in Roma Intense', detail: 'EDP · 100ml Importado', price: '8,500', image: 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Exclusivo' },
                                     { id: 3, brand: 'VERSACE', name: 'Eros Flame Pour Homme', detail: 'EDP · 100ml Sellado', price: '5,900', image: 'https://images.unsplash.com/photo-1547887537-6158d64c35b3?auto=format&fit=crop&w=400&h=400&q=80', tag: 'En stock' },
                                     { id: 4, brand: 'ARMAF', name: 'Club de Nuit Intense Man', detail: 'EDT · 105ml Clásico', price: '3,200', image: 'https://images.unsplash.com/photo-1616949755610-8c9bbc08f138?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Popular' }
                                 ]
                             },
                             tenis: {
                                 name: 'Tenis & Calzado',
                                 sub: 'Talla · Modelo · Colorway',
                                 storeName: 'Bsolutions Kicks',
                                 tag: 'Sneakers exclusivos y calzado urbano',
                                 avatar: 'BK',
                                 color: 'from-orange-600 to-amber-600',
                                 cats: ['Todo (35)', 'Nike (15)', 'Adidas (10)', 'New Balance (10)'],
                                 brands: ['Nike', 'Adidas', 'New Balance', 'Jordan'],
                                 products: [
                                     { id: 13, brand: 'NIKE', name: 'Air Max 270 React Triple Black', detail: 'Talla 42 (US 9) · Nuevo en caja', price: '4,800', image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Top Ventas' },
                                     { id: 14, brand: 'ADIDAS', name: 'Samba OG Classic White/Black', detail: 'Talla 41 (US 8.5) · Cuero genuino', price: '5,200', image: 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Tendencia' },
                                     { id: 15, brand: 'NEW BALANCE', name: '550 Vintage White Green', detail: 'Talla 43 (US 9.5) · Edición Retro', price: '4,950', image: 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=400&h=400&q=80', tag: 'En stock' },
                                     { id: 16, brand: 'JORDAN', name: 'Air Jordan 1 Retro High Chicago', detail: 'Talla 42.5 (US 9) · Cuero Premium', price: '8,900', image: 'https://images.unsplash.com/photo-1552346154-21d32810aba3?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Exclusivo' }
                                 ]
                             },
                             celulares: {
                                 name: 'Celulares & Tech',
                                 sub: 'Modelo · Almacenamiento · Condición',
                                 storeName: 'iShop Móvil RD',
                                 tag: 'iPhones, Accesorios y Gadgets Garantizados',
                                 avatar: 'IM',
                                 color: 'from-blue-600 to-cyan-600',
                                 cats: ['Todo (41)', 'iPhones (18)', 'AirPods (7)', 'Accesorios (16)'],
                                 brands: ['Apple', 'Samsung', 'Anker', 'JBL'],
                                 products: [
                                     { id: 9, brand: 'APPLE', name: 'iPhone 15 Pro Max Titanium', detail: '256GB · Sellado · Garantía 1 año', price: '58,000', image: 'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Nuevo' },
                                     { id: 10, brand: 'APPLE', name: 'AirPods Pro 2da Gen USB-C', detail: 'Cancelación Activa de Ruido', price: '12,500', image: 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Original' },
                                     { id: 11, brand: 'APPLE', name: 'Apple Watch Series 9 45mm', detail: 'Aluminio Midnight · Sensor Salud', price: '21,000', image: 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?auto=format&fit=crop&w=400&h=400&q=80', tag: 'En stock' },
                                     { id: 12, brand: 'ANKER', name: 'Cargador Rápido GaN 30W USB-C', detail: 'Cable Trenzado alta durabilidad', price: '1,650', image: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Accesorio' }
                                 ]
                             },
                             ropa: {
                                 name: 'Ropa & Boutique',
                                 sub: 'Tipo · Talla · Color',
                                 storeName: 'Moda Urbana RD',
                                 tag: 'Prendas exclusivas, lino y streetwear',
                                 avatar: 'MU',
                                 color: 'from-emerald-600 to-teal-600',
                                 cats: ['Todo (50)', 'Camisas (20)', 'Pantalones (15)', 'Bermudas (15)'],
                                 brands: ['Zara Man', 'Lino Premium', 'Streetwear RD', 'Polo Club'],
                                 products: [
                                     { id: 21, brand: 'LINO PREMIUM', name: 'Camisa Lino Manga Corta Slim', detail: '100% Lino Transpirable · Talla M', price: '1,450', image: 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Fresco' },
                                     { id: 22, brand: 'STREETWEAR RD', name: 'Pantalón Cargo Oversized Negro', detail: 'Bolsillos laterales · Talla 32', price: '2,200', image: 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Top Ventas' },
                                     { id: 23, brand: 'POLO CLUB', name: 'Polo Piqué Algodón Pima', detail: 'Cuello clásico · Talla L · Azul Marino', price: '1,150', image: 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Básico' },
                                     { id: 24, brand: 'STREETWEAR RD', name: 'Chaqueta Bomber Street Urbana', detail: 'Cierre frontal · Forro térmico · Talla M', price: '3,400', image: 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Nuevo' }
                                 ]
                             },
                             joyeria: {
                                 name: 'Joyería & Relojes',
                                 sub: 'Material · Tipo · Género',
                                 storeName: 'Aureum Joyería Fina',
                                 tag: 'Plata Italiana 925 y Oro Laminado 18k',
                                 avatar: 'AJ',
                                 color: 'from-amber-500 to-yellow-600',
                                 cats: ['Todo (28)', 'Cadenas (10)', 'Anillos (8)', 'Relojes (10)'],
                                 brands: ['Aureum', 'Casio Vintage', 'Silver Italy', 'Tissot'],
                                 products: [
                                     { id: 17, brand: 'SILVER ITALY', name: 'Cadena Cubana Maciza 60cm', detail: 'Plata Ley 925 · 8mm de grosor', price: '4,200', image: 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Plata 925' },
                                     { id: 18, brand: 'AUREUM', name: 'Anillo Solitario Circón Suizo', detail: 'Baño de Oro 18K · Talla 7', price: '1,850', image: 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Garantía' },
                                     { id: 19, brand: 'CASIO', name: 'Reloj Vintage Digital Dorado', detail: 'Acero Inoxidable · Alarma & Crono', price: '2,900', image: 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Clásico' },
                                     { id: 20, brand: 'AUREUM', name: 'Aretes Perla Cultivada Plata 925', detail: 'Broche mariposa seguro · Hipoalergénico', price: '1,350', image: 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&w=400&h=400&q=80', tag: 'En stock' }
                                 ]
                             },
                             reposteria: {
                                 name: 'Repostería & Café',
                                 sub: 'Tipo · Sabor · Porciones',
                                 storeName: 'Dulce Antojo Bakery',
                                 tag: 'Pastelería artesanal y postres fríos',
                                 avatar: 'DA',
                                 color: 'from-pink-600 to-rose-600',
                                 cats: ['Todo (24)', 'Pasteles (10)', 'Postres Fríos (8)', 'Cafetería (6)'],
                                 brands: ['Artesanal', 'Frutas Frescas', 'Chocolate Belga', 'Gourmet'],
                                 products: [
                                     { id: 25, brand: 'ARTESANAL', name: 'Cheesecake Frutos Rojos Familiar', detail: '8 a 10 Porciones · Con compota natural', price: '1,250', image: 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Favorito' },
                                     { id: 26, brand: 'GOURMET', name: 'Pastel Gourmet Tres Leches', detail: 'Merengue flameado con canela · Individual', price: '275', image: 'https://images.unsplash.com/photo-1464349095431-e9a21285b5f3?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Fresco' },
                                     { id: 27, brand: 'CHOCOLATE BELGA', name: 'Tarta Chocolate 70% Ganache', detail: 'Bizcocho húmedo · 10 Porciones', price: '1,600', image: 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Premium' },
                                     { id: 28, brand: 'ARTESANAL', name: 'Caja de 6 Cupcakes Red Velvet', detail: 'Frosting de queso crema suave', price: '650', image: 'https://images.unsplash.com/photo-1587668178277-295251f900ce?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Regalo' }
                                 ]
                             },
                             vapes: {
                                 name: 'Vapes & Pods',
                                 sub: 'Tipo · Nicotina · Capacidad',
                                 storeName: 'Cloud Pods RD',
                                 tag: 'Vape Shop & Dispositivos Desechables',
                                 avatar: 'CP',
                                 color: 'from-purple-600 to-indigo-600',
                                 cats: ['Todo (32)', 'Desechables (18)', 'Pods Recargables (8)', 'Líquidos (6)'],
                                 brands: ['Lost Mary', 'Geek Bar', 'Elf Bar', 'Oxbar'],
                                 products: [
                                     { id: 5, brand: 'LOST MARY', name: 'MO20000 Pro Ice Watermelon', detail: '20,000 Puffs · 5%', price: '1,350', image: 'https://images.unsplash.com/photo-1559818454-1b3a36b94e43?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Nuevo' },
                                     { id: 6, brand: 'GEEK BAR', name: 'Pulse 15000 Blow Pop', detail: '15,000 Puffs · Modo Pulse', price: '1,200', image: 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Top Ventas' },
                                     { id: 7, brand: 'OXBAR', name: 'Magic Maze 2.0 Blue Razz', detail: '30,000 Puffs · Display LED', price: '1,450', image: 'https://images.unsplash.com/photo-1527661591475-527312dd65f5?auto=format&fit=crop&w=400&h=400&q=80', tag: 'En stock' },
                                     { id: 8, brand: 'ELF BAR', name: 'BC5000 Strawberry Kiwi', detail: '5,000 Puffs · Type-C', price: '850', image: 'https://images.unsplash.com/photo-1563245372-f21724e3856d?auto=format&fit=crop&w=400&h=400&q=80', tag: 'Oferta' }
                                 ]
                             }
                         },
                         addToCartSim(id) {
                             if (!this.itemsInCart[id]) {
                                 this.itemsInCart[id] = 1;
                                 this.cartCount++;
                             } else {
                                 this.itemsInCart[id]++;
                             }
                             this.waNotification = true;
                             setTimeout(() => { this.waNotification = false; }, 3000);
                         },
                         removeFromCartSim(id) {
                             if (this.itemsInCart[id] > 1) {
                                 this.itemsInCart[id]--;
                             } else if (this.itemsInCart[id] === 1) {
                                 delete this.itemsInCart[id];
                                 this.cartCount = Math.max(0, this.cartCount - 1);
                             }
                         }
                     }">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Section Title & Headline -->
                    <div class="max-w-3xl mb-12">
                        <span class="text-xs font-bold text-amber-800 bg-amber-100/80 border border-amber-200 px-3.5 py-1 rounded-full uppercase tracking-widest">
                            Tu negocio, organizado a tu manera
                        </span>
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight mt-3">
                            Herramientas para lo que vendes.
                        </h2>
                        <p class="text-base sm:text-lg text-slate-600 mt-3 leading-relaxed max-w-2xl">
                            Explora estos ejemplos interactivos de perfumes, ropa y tecnología. Los productos, imágenes y precios son ilustrativos, no ventas reales ni testimonios.
                        </p>
                    </div>

                    <!-- Layout: 3 Columns (Rubros List | Smartphone Center | Filters Drawer Right) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                        
                        <!-- Col 1: Categories / Niches Vertical Selector (Left, 4 cols) -->
                        <div class="lg:col-span-4 flex flex-col gap-2">
                            <!-- Mobile horizontal scroll tab for categories -->
                            <div class="flex lg:hidden overflow-x-auto gap-2 pb-2 no-scrollbar">
                                <template x-for="(data, key) in rubros" :key="key">
                                    <button 
                                        type="button" 
                                        @click="activeRubro = key"
                                        :class="activeRubro === key ? 'bg-slate-900 text-white font-extrabold shadow-md' : 'bg-white text-slate-700 border border-slate-200'"
                                        class="px-4 py-2.5 rounded-2xl text-xs font-bold whitespace-nowrap transition cursor-pointer"
                                        x-text="data.name"
                                    ></button>
                                </template>
                            </div>

                            <!-- Desktop vertical card list -->
                            <div class="hidden lg:flex flex-col gap-1.5">
                                <template x-for="(data, key) in rubros" :key="key">
                                    <button 
                                        type="button" 
                                        @click="activeRubro = key"
                                        :class="activeRubro === key ? 'bg-amber-50/90 border-amber-300 ring-2 ring-amber-500/20 shadow-xs' : 'bg-transparent border-transparent hover:bg-white hover:border-slate-200 text-slate-600'"
                                        class="w-full text-left p-3.5 rounded-2xl border transition-all duration-150 flex flex-col gap-0.5 cursor-pointer group"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span 
                                                class="text-base font-extrabold transition-colors"
                                                :class="activeRubro === key ? 'text-amber-950' : 'text-slate-900 group-hover:text-blue-600'"
                                                x-text="data.name"
                                            ></span>
                                            <span x-show="activeRubro === key" class="text-xs text-amber-700 font-black">● Activa</span>
                                        </div>
                                        <span class="text-xs text-slate-500 font-medium" x-text="data.sub"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Col 2: The Real Smartphone Mockup Frame (Center, 5 cols) -->
                        <div class="lg:col-span-5 flex flex-col items-center">
                            <!-- Floating Pill Badge over phone -->
                            <div class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3.5 py-1 text-[11px] font-bold text-white shadow-lg mb-3">
                                <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span class="uppercase tracking-widest text-[10px]">Demo en vivo — Pruébala</span>
                            </div>

                            <!-- Phone Outer Shell (iPhone Mockup Realista) -->
                            <div class="relative w-full max-w-[340px] sm:max-w-[360px] bg-slate-950 rounded-[52px] p-3 shadow-2xl border-[10px] border-slate-900 ring-1 ring-slate-800/80">
                                <!-- Dynamic Island Notch -->
                                <div class="absolute top-4 left-1/2 -translate-x-1/2 w-24 h-4 bg-black rounded-full z-30 flex items-center justify-end pr-2 gap-1.5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500/80"></span>
                                </div>

                                <!-- Screen Inside -->
                                <div class="bg-[#F8FAFC] rounded-[42px] overflow-hidden flex flex-col h-[600px] relative text-slate-900 select-none border border-slate-200/50">
                                    <!-- Toast Alert on item added -->
                                    <div 
                                        x-show="waNotification" 
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-4"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 -translate-y-4"
                                        class="absolute top-8 inset-x-3 z-30 bg-emerald-600 text-white p-2.5 rounded-2xl shadow-xl flex items-center justify-between text-xs font-bold gap-2"
                                    >
                                        <div class="flex items-center gap-2 truncate">
                                            <span>🛍️</span>
                                            <span class="truncate">¡Producto agregado al pedido!</span>
                                        </div>
                                        <button @click="waNotification = false" class="text-white/80 font-bold p-1">✕</button>
                                    </div>

                                    <!-- Top Phone Bar (Clock, Wifi, Battery) -->
                                    <div class="flex items-center justify-between px-6 pt-3 pb-1 text-[11px] font-bold text-slate-600 shrink-0">
                                        <span>9:41</span>
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                                            <span class="w-4 h-2 rounded-xs border border-slate-600 p-0.5 flex items-center"><span class="w-full h-full bg-slate-700"></span></span>
                                        </div>
                                    </div>

                                    <!-- Storefront Header inside Phone -->
                                    <div class="p-3 bg-white border-b border-slate-200/80 flex items-center justify-between gap-2 shrink-0">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div 
                                                class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-black text-xs shadow-xs shrink-0 bg-gradient-to-br"
                                                :class="rubros[activeRubro].color"
                                                x-text="rubros[activeRubro].avatar"
                                            ></div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1">
                                                    <span class="font-extrabold text-xs text-slate-900 truncate" x-text="rubros[activeRubro].storeName"></span>
                                                    <svg class="w-3 h-3 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                </div>
                                                <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Abierto hoy
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Cart Pill Button inside Phone -->
                                        <button 
                                            type="button" 
                                            class="relative flex items-center justify-center w-8 h-8 rounded-xl bg-slate-900 text-white shadow-xs cursor-pointer active:scale-95 transition"
                                            title="Ver pedido"
                                        >
                                            <span class="text-xs">🛍️</span>
                                            <span 
                                                class="absolute -top-1 -right-1 h-4 min-w-4 px-1 rounded-full bg-blue-600 text-white text-[9px] font-black flex items-center justify-center border border-white"
                                                x-text="cartCount"
                                            ></span>
                                        </button>
                                    </div>

                                    <!-- Search & Filter Bar inside Phone -->
                                    <div class="px-3 pt-2.5 pb-2 bg-white flex items-center gap-2 shrink-0 border-b border-slate-100">
                                        <div class="flex-1 bg-slate-100/90 rounded-xl px-2.5 py-1.5 flex items-center gap-1.5 text-slate-400 text-[11px]">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            <span class="truncate">Buscar productos, marcas...</span>
                                        </div>
                                        <button 
                                            type="button" 
                                            @click="filterOpen = !filterOpen"
                                            class="p-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50 transition cursor-pointer"
                                            title="Filtros"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                        </button>
                                    </div>

                                    <!-- Category Pill Tabs inside Phone -->
                                    <div class="px-3 py-2 bg-white flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0 border-b border-slate-100">
                                        <template x-for="(cat, idx) in rubros[activeRubro].cats" :key="idx">
                                            <button 
                                                type="button" 
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap transition"
                                                :class="idx === 0 ? 'bg-slate-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                                x-text="cat"
                                            ></button>
                                        </template>
                                    </div>

                                    <!-- Product 2-Column Grid inside Phone (With REAL HD Photos like Puntto) -->
                                    <div class="flex-1 overflow-y-auto p-3 space-y-2.5">
                                        <div class="grid grid-cols-2 gap-2">
                                            <template x-for="p in rubros[activeRubro].products" :key="p.id">
                                                <div class="bg-white rounded-2xl p-2 border border-slate-200/90 shadow-2xs flex flex-col justify-between hover:border-blue-400 transition group">
                                                    <div>
                                                        <!-- Product Visual Image Container -->
                                                        <div class="w-full aspect-square rounded-xl bg-slate-100 relative overflow-hidden border border-slate-100 shadow-2xs">
                                                            <img :src="p.image" :alt="p.name" class="w-full h-full object-cover object-center group-hover:scale-105 transition duration-300" loading="lazy">
                                                            <span class="absolute top-1.5 left-1.5 rounded-md bg-slate-900/80 backdrop-blur-xs text-white text-[8px] font-black px-1.5 py-0.5" x-text="p.tag"></span>
                                                        </div>

                                                        <!-- Brand & Name -->
                                                        <div class="mt-2">
                                                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block" x-text="p.brand"></span>
                                                            <h4 class="text-[11px] font-bold text-slate-900 line-clamp-2 leading-tight" x-text="p.name"></h4>
                                                            <p class="text-[9px] text-slate-500 truncate mt-0.5" x-text="p.detail"></p>
                                                        </div>
                                                    </div>

                                                    <!-- Price & Cart Button -->
                                                    <div class="mt-2 pt-1.5 border-t border-slate-100">
                                                        <p class="text-xs font-black text-slate-900 tabular-nums whitespace-nowrap">
                                                            RD$ <span x-text="p.price"></span>
                                                        </p>

                                                        <!-- Action: Agregar or Stepper -->
                                                        <div class="mt-1.5">
                                                            <template x-if="!itemsInCart[p.id]">
                                                                <button 
                                                                    type="button" 
                                                                    @click="addToCartSim(p.id)"
                                                                    class="w-full py-1.5 px-2 rounded-lg bg-slate-900 hover:bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center gap-1 transition active:scale-95 cursor-pointer shadow-2xs"
                                                                >
                                                                    <span>+ Agregar</span>
                                                                </button>
                                                            </template>
                                                            <template x-if="itemsInCart[p.id]">
                                                                <div class="w-full flex items-center justify-between rounded-lg bg-amber-50 border border-amber-200 p-0.5">
                                                                    <button type="button" @click="removeFromCartSim(p.id)" class="w-5 h-5 flex items-center justify-center text-xs font-bold text-amber-900 hover:bg-amber-200 rounded">-</button>
                                                                    <span class="text-[10px] font-black text-amber-900 tabular-nums" x-text="itemsInCart[p.id]"></span>
                                                                    <button type="button" @click="addToCartSim(p.id)" class="w-5 h-5 flex items-center justify-center text-xs font-bold text-amber-900 hover:bg-amber-200 rounded">+</button>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- WhatsApp Send Order CTA inside phone footer -->
                                        <div class="pt-1">
                                            <a 
                                                :href="'https://wa.me/18298144525?text=' + encodeURIComponent('Hola ' + rubros[activeRubro].storeName + ', me interesa hacer un pedido de ' + cartCount + ' artículos de su catálogo.')"
                                                target="_blank"
                                                class="w-full py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-extrabold flex items-center justify-center gap-1.5 shadow-xs transition"
                                            >
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                                <span>Enviar pedido por WhatsApp</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Col 3: The Floating Filters Drawer / Panel (Right, 3 cols) -->
                        <div class="lg:col-span-3 hidden lg:flex flex-col">
                            <div class="bg-white rounded-3xl p-5 border border-slate-200/90 shadow-xl space-y-4 sticky top-28">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <h3 class="text-sm font-extrabold text-slate-900">Filtros</h3>
                                    <span class="text-xs text-slate-400 hover:text-slate-600 cursor-pointer">✕</span>
                                </div>

                                <!-- Ordenar -->
                                <div class="space-y-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Ordenar</span>
                                    <div class="space-y-1.5 text-xs">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="sim_sort" value="recientes" checked class="text-blue-600 focus:ring-blue-500">
                                            <span class="font-bold text-slate-800">Más recientes</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="sim_sort" value="menor" class="text-blue-600 focus:ring-blue-500">
                                            <span class="text-slate-600">Precio: de menor a mayor</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="sim_sort" value="mayor" class="text-blue-600 focus:ring-blue-500">
                                            <span class="text-slate-600">Precio: de mayor a menor</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="sim_sort" value="nombre" class="text-blue-600 focus:ring-blue-500">
                                            <span class="text-slate-600">Nombre (A-Z)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Marcas / Atributos del Rubro -->
                                <div class="space-y-2 pt-2 border-t border-slate-100">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Marcas destacadas</span>
                                    <div class="space-y-1.5 text-xs max-h-36 overflow-y-auto">
                                        <template x-for="(brand, idx) in rubros[activeRubro].brands" :key="idx">
                                            <label class="flex items-center justify-between cursor-pointer py-0.5">
                                                <div class="flex items-center gap-2">
                                                    <input type="checkbox" class="rounded text-blue-600 focus:ring-blue-500">
                                                    <span class="text-slate-700" x-text="brand"></span>
                                                </div>
                                                <span class="text-[10px] text-slate-400" x-text="idx + 2"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                                    <button type="button" class="text-xs text-slate-500 hover:text-slate-800 font-semibold cursor-pointer">Limpiar</button>
                                    <button type="button" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold shadow-xs hover:bg-blue-600 transition cursor-pointer">
                                        Ver productos
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- 2. SECCIÓN DEL PROBLEMA ("¿Publicas los mismos productos una y otra vez?") -->
            <section class="w-full bg-slate-100/70 border-y border-slate-200/80 py-16 lg:py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center">
                    <div class="max-w-3xl text-center flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-rose-600 uppercase tracking-widest bg-rose-50 border border-rose-200 px-3 py-1 rounded-full">
                            El problema del vendedor informal
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mt-1">
                            ¿Publicas los mismos productos una y otra vez?
                        </h2>
                        <p class="text-base sm:text-lg text-slate-600 max-w-2xl leading-relaxed">
                            Tus estados desaparecen, tus publicaciones se pierden y terminas compartiendo las mismas imágenes constantemente.
                        </p>
                    </div>

                    <!-- Comparativa 2 Bloques -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 w-full mt-12 max-w-5xl">
                        <!-- Bloque Izquierdo: La rutina agotadora -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-sm flex flex-col gap-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-2.5 text-rose-600">
                                <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600 font-bold border border-rose-100">
                                    ✕
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">La rutina agotadora</h3>
                            </div>
                            <div class="flex flex-col gap-5">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs border border-rose-100">
                                        1
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Buscar fotos en la galería</h4>
                                        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Revisas cientos de fotos en tu celular para encontrar el producto exacto que te piden.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs border border-rose-100">
                                        2
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Subir a estados y stories</h4>
                                        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Inundas los estados de WhatsApp de tus contactos con 40 fotos seguidas.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs border border-rose-100">
                                        3
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Repetir todo mañana</h4>
                                        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">A las 24 horas desaparecen y ningún cliente nuevo puede volver a verlas.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloque Derecho: Con MiCatalogo -->
                        <div class="bg-white p-8 rounded-3xl border border-blue-200/90 shadow-md ring-1 ring-blue-500/10 flex flex-col gap-6 hover:shadow-lg transition-shadow">
                            <div class="flex items-center gap-2.5 text-blue-600">
                                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold border border-blue-100">
                                    ✓
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">Con MiCatalogo</h3>
                            </div>
                            <div class="flex flex-col gap-5">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 font-bold text-xs border border-blue-100">
                                        1
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Subes una sola vez</h4>
                                        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Cargas tus fotos, defines nombre y precio. Quedan organizadas de por vida.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 font-bold text-xs border border-emerald-100">
                                        2
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Actualizas con un toque</h4>
                                        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">¿Se agotó un producto o subió el precio? Lo cambias en 5 segundos.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 font-bold text-xs border border-indigo-100">
                                        3
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Un enlace permanente</h4>
                                        <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Tus clientes abren tu enlace cuando quieran y ven todo tu catálogo al día.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 3. CÓMO FUNCIONA ("Tu catálogo listo en pocos pasos") -->
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24" id="como-funciona">
                <div class="flex flex-col items-center gap-12">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Simplicidad total
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Tu catálogo listo en pocos pasos
                        </h2>
                        <p class="text-base text-slate-600">
                            Sin conocimientos técnicos. Empieza a compartir en menos de 5 minutos.
                        </p>
                    </div>

                    <!-- 3 Pasos Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 w-full">
                        <!-- Paso 1 -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-4 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-extrabold text-lg border border-blue-100">
                                01
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Paso 01</span>
                            <h3 class="text-xl font-bold text-slate-900">Crea tu catálogo</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Regístrate gratis y agrega los datos básicos de tu negocio, tu logo y tu número de WhatsApp para recibir pedidos directos.
                            </p>
                        </div>

                        <!-- Paso 2 -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-4 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-extrabold text-lg border border-indigo-100">
                                02
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Paso 02</span>
                            <h3 class="text-xl font-bold text-slate-900">Sube tus productos</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Añade fotos desde tu galería, ponle nombre, precio y marca si está disponible o con poco stock con optimización automática.
                            </p>
                        </div>

                        <!-- Paso 3 -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-4 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-extrabold text-lg border border-emerald-100">
                                03
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Paso 03</span>
                            <h3 class="text-xl font-bold text-slate-900">Comparte tu enlace</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Pega tu link en la bio de Instagram, estados de WhatsApp o envíalo por chat a quien te pregunte por tus productos.
                            </p>
                        </div>
                    </div>

                    <!-- CTA Botón -->
                    <div class="flex flex-col items-center gap-2 pt-2">
                        <a class="bg-blue-600 text-white font-bold text-sm px-8 py-3.5 rounded-xl shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-all active:scale-98" href="{{ route('register') }}">
                            Crear mi catálogo ahora
                        </a>
                        <span class="text-xs text-slate-500">Configuración instantánea en 3 minutos</span>
                    </div>
                </div>
            </section>

            <!-- 4. BENEFICIO PRINCIPAL: Flujo de chat WhatsApp + Catálogo (Pedidos por WhatsApp) -->
            <section class="w-full bg-slate-100/70 border-y border-slate-200/80 py-16 lg:py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                        <div class="lg:col-span-5 flex flex-col gap-5">
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full self-start">
                                Responde al instante
                            </span>
                            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                                Un solo enlace para todos tus productos y Pedidos por WhatsApp
                            </h2>
                            <p class="text-base sm:text-lg text-slate-600 leading-relaxed">
                                Cuando un cliente te pregunte por WhatsApp <em>"¿Qué tienes disponible?"</em>, no pierdas 15 minutos enviando 20 fotos individuales. Envía tu enlace directo y deja que explore tu vitrina con calma. Tus clientes realizan sus <strong>Pedidos por WhatsApp</strong> de forma inmediata.
                            </p>
                            <div class="flex flex-col gap-3 pt-1">
                                <div class="flex items-center gap-3 text-slate-800 text-sm font-semibold">
                                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">✓</div>
                                    <span>Precios claros que evitan preguntas repetitivas</span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-800 text-sm font-semibold">
                                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">✓</div>
                                    <span>El cliente elige y te escribe con el producto exacto</span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-800 text-sm font-semibold">
                                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">✓</div>
                                    <span>Cero fricción: no requiere descargar aplicaciones</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mockup Interactivo WhatsApp Visual -->
                        <div class="lg:col-span-7 flex justify-center">
                            <div class="w-full max-w-lg bg-white rounded-3xl p-6 sm:p-7 shadow-xl border border-slate-200 flex flex-col gap-4">
                                <!-- Chat Header -->
                                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                                    <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                        C
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-900">Cliente en WhatsApp</span>
                                        <span class="text-[11px] text-emerald-600 font-medium">● En línea</span>
                                    </div>
                                </div>

                                <!-- Bubbles -->
                                <div class="self-start max-w-[85%] bg-slate-100 rounded-2xl rounded-tl-none p-3.5 shadow-2xs">
                                    <p class="text-xs sm:text-sm text-slate-800">
                                        ¡Hola! ¿Qué productos tienes disponibles para entrega inmediata hoy? 👀
                                    </p>
                                    <span class="text-[10px] text-slate-400 text-right block mt-1">10:14 AM</span>
                                </div>

                                <div class="self-end max-w-[90%] bg-blue-50 border border-blue-100 rounded-2xl rounded-tr-none p-3.5 shadow-2xs">
                                    <p class="text-xs sm:text-sm text-slate-900">
                                        ¡Hola! Puedes ver todo nuestro catálogo actualizado con precios aquí 👇
                                    </p>
                                    <!-- Rich Link Preview -->
                                    <div class="mt-2.5 bg-white rounded-xl p-3 border border-blue-200/80 shadow-xs flex flex-col gap-1">
                                        <span class="text-xs font-bold text-blue-600">micatalogo.bsolutions.dev/tienda/bsolutions-dev</span>
                                        <span class="text-[11px] text-slate-600 line-clamp-1">Catálogo oficial de Bsolutions • Tecnología y accesorios</span>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[10px] font-semibold text-emerald-600">● 24 productos en stock</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-blue-600 text-right block mt-1 font-semibold">10:15 AM ✓✓</span>
                                </div>

                                <div class="self-start max-w-[85%] bg-slate-100 rounded-2xl rounded-tl-none p-3.5 shadow-2xs">
                                    <p class="text-xs sm:text-sm text-slate-800">
                                        ¡Perfecto! Me interesan los <strong>Nike Air Max 270</strong> en talla 42. ¿Cómo coordinamos el envío? 📦
                                    </p>
                                    <span class="text-[10px] text-slate-400 text-right block mt-1">10:17 AM</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. PLANES -->
            @php
                $planFeatures = [
                    'Tu tienda + catálogo',
                    'Ventas y facturación',
                    'Control de inventario',
                    'Reportes de ventas',
                    'Cotizaciones',
                    'Tu tienda online con pedidos por WhatsApp',
                    'Pedidos',
                    'Clientes y suplidores',
                    'Funciona en tu celular y computadora',
                ];
                $planNames = [
                    'free' => 'Básico',
                    'premium' => 'Premium',
                    'pro' => 'Pro',
                ];
            @endphp
            <section class="w-full bg-[#f6f6f7] px-4 py-16 sm:px-6 lg:px-8 lg:py-24" id="planes">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600">Planes claros y sin sorpresas</span>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Empieza gratis. Crece cuando lo necesites.</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-600 sm:text-base">Elige las herramientas que necesitas para llevar tu catálogo y tus ventas con más control.</p>
                    </div>

                    <div class="mx-auto mt-10 grid max-w-6xl gap-5 lg:grid-cols-3 lg:items-stretch">
                        @foreach(config('catalog.plans') as $key => $limits)
                            @php
                                $isPro = $key === 'pro';
                                $isFree = $key === 'free';
                                $features = $planFeatures;
                                $features[] = number_format($limits['max_products_per_shop']) . ' productos incluidos';
                                $features[] = $limits['max_images_per_product'] . ' imágenes por producto';
                                if ($isPro) {
                                    $features[] = 'Reglas de precio por margen y redondeo';
                                    $features[] = 'Bajadas de precio con aprobación';
                                }
                            @endphp
                            <article class="relative flex h-full flex-col rounded-2xl border {{ $isPro ? 'border-blue-600 shadow-[0_18px_45px_rgba(37,99,235,0.16)]' : 'border-slate-200 shadow-[0_8px_24px_rgba(15,23,42,0.06)]' }} bg-white p-7 sm:p-8">
                                @if($isPro)
                                    <span class="absolute -top-3 left-7 rounded-full bg-blue-600 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-white shadow-sm">Más completo</span>
                                @endif
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-extrabold text-slate-950">{{ $planNames[$key] ?? \App\Enums\UserPlan::from($key)->label() }}</h3>
                                        <p class="mt-1 text-xs font-medium text-slate-500">{{ $isFree ? 'Para comenzar sin costo' : ($isPro ? 'Para negocios en crecimiento' : 'Para operar con más capacidad') }}</p>
                                    </div>
                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $limits['max_active_shops'] }} tienda</span>
                                </div>

                                <div class="mt-7 flex items-baseline gap-2 border-b border-slate-100 pb-6">
                                    <span class="text-[2.65rem] font-black leading-none tracking-[-0.06em] text-slate-950">{{ $isFree ? 'RD$ 0' : 'Consultar' }}</span>
                                    <span class="text-sm text-slate-500">{{ $isFree ? 'sin costo' : 'activación' }}</span>
                                </div>

                                <ul class="mt-6 flex-1 space-y-3.5 text-sm text-slate-700">
                                    @foreach($features as $feature)
                                        <li class="flex items-start gap-3 leading-5">
                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m5 12 4 4L19 6"/></svg>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                <a href="{{ route('register') }}" class="mt-8 inline-flex min-h-11 items-center justify-center rounded-xl border {{ $isPro ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700' : 'border-slate-300 bg-white text-slate-900 hover:border-slate-900' }} px-4 py-3 text-sm font-extrabold transition-colors">
                                    Crear cuenta
                                </a>
                            </article>
                        @endforeach
                    </div>
                    <p class="mx-auto mt-6 max-w-3xl text-center text-xs leading-5 text-slate-500">No hay cobro automático de suscripciones. Las activaciones Premium y Pro se coordinan con el administrador. No se anuncian cuotas de almacenamiento o usuarios que el sistema no controle.</p>
                </div>
            </section>
            <section class="mx-auto max-w-7xl px-4 py-10"><h2 class="text-3xl font-bold">Administra también desde Android</h2><p class="mt-3">Descarga tu catálogo y registra ventas desde el teléfono. Las ventas y nuevos abonos se envían cuando hay conexión; los conflictos requieren revisión. La administración completa y los reportes FIFO están en el panel web.</p><a class="mt-4 inline-flex rounded-xl bg-blue-700 px-6 py-3 font-semibold text-white" href="{{ route('downloads.index') }}">Ver versión y descargar APK</a></section>
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24" id="ventajas">
                <div class="flex flex-col items-center gap-12">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Todo en uno
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Herramientas para vender y mantener el control
                        </h2>
                        <p class="text-base text-slate-600">
                            Catálogo, ventas, existencias, comprobantes PDF, clientes y crédito, vendedores e importación CSV/XLSX.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 w-full">
                        <!-- Card 1 -->
                        <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-3 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Catálogo siempre disponible</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Tus clientes pueden ver tus productos cuando quieran, sin esperar a que vuelvas a publicarlos en estados temporales.
                            </p>
                        </div>

                        <!-- Card 2 -->
                        <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-3 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Fácil de compartir</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Un único enlace listo para copiar y pegar en WhatsApp, Instagram, Facebook o enviarlo directamente a un cliente.
                            </p>
                        </div>

                        <!-- Card 3 -->
                        <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-3 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center border border-sky-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Actualiza cuando quieras</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Cambia precios, fotos, disponibilidad o agrega nuevos artículos en segundos sin alterar el enlace que ya compartiste.
                            </p>
                        </div>

                        <!-- Card 4 -->
                        <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-3 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Control básico de inventario</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Registra fácilmente las unidades vendidas y visualiza qué productos tienen poco stock o ya se agotaron.
                            </p>
                        </div>

                        <!-- Card 5 -->
                        <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-3 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Contacto directo por WhatsApp</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                El cliente consulta un producto específico directamente a tu WhatsApp con un solo toque y mensaje precargado.
                            </p>
                        </div>

                        <!-- Card 6 -->
                        <div class="bg-white p-7 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-3 hover:-translate-y-1.5 hover:shadow-xl hover:border-blue-200 transition-all duration-300">
                            <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center border border-teal-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Empieza gratis. Sin tarjeta.</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Puedes crear tu catálogo hoy mismo sin suscripciones forzosas, sin intermediarios y sin ingresar tarjeta de crédito.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 8. AISLAMIENTO DEL CATÁLOGO (Espacio propio de marca) -->
            <section class="w-full bg-slate-100/70 border-y border-slate-200/80 py-16 lg:py-24">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center text-center gap-6"
                     x-data="{ copied: false, copyLink() { navigator.clipboard.writeText('https://micatalogo.bsolutions.dev/tienda/bsolutions-dev'); this.copied = true; setTimeout(() => this.copied = false, 3000); } }">
                    <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-700 border border-blue-100 px-3.5 py-1.5 rounded-full text-xs font-semibold">
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Sin distracciones ni competencia</span>
                    </div>

                    <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight max-w-2xl">
                        Tu catálogo es tu espacio: Vitrina 100% Aislada
                    </h2>

                    <p class="text-base sm:text-lg text-slate-600 max-w-2xl leading-relaxed">
                        Cuando compartes tu enlace, tus clientes ven únicamente tus productos. MiCatalogo no es un marketplace: jamás les mostrará tiendas competidoras dentro de tu vitrina.
                    </p>

                    <!-- Trust Flow Diagram with Interactive Copy Link -->
                    <div class="w-full bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6 my-2">
                        <div class="flex flex-col items-center md:items-start text-center md:text-left">
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Tu Enlace Exclusivo</span>
                            <span class="text-sm sm:text-base font-bold text-blue-600 mt-1">micatalogo.bsolutions.dev/tienda/tu-tienda</span>
                            <button type="button" @click="copyLink()" class="mt-2 text-xs font-semibold text-slate-600 hover:text-blue-600 flex items-center gap-1.5 cursor-pointer">
                                <span x-show="!copied">📋 Copiar enlace de demostración</span>
                                <span x-show="copied" x-cloak class="text-emerald-600 font-bold">✓ ¡Enlace copiado al portapapeles!</span>
                            </button>
                        </div>

                        <div class="hidden md:flex items-center text-slate-300">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="px-5 py-2 bg-slate-100 text-slate-900 rounded-xl font-bold text-sm border border-slate-200">
                                TU MARCA OFICIAL
                            </div>
                            <span class="text-xs text-slate-500 mt-1">Tu identidad en primer plano</span>
                        </div>

                        <div class="hidden md:flex items-center text-slate-300">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </div>

                        <div class="flex flex-col items-center md:items-end text-center md:text-right">
                            <span class="text-xs text-emerald-600 font-bold uppercase tracking-wider">100% Privado</span>
                            <span class="text-sm sm:text-base font-bold text-slate-900 mt-1">Solo tus propios productos</span>
                        </div>
                    </div>

                    <div class="inline-flex items-center gap-2 text-slate-800 text-sm font-semibold">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Tus clientes siguen siendo tus clientes.</span>
                    </div>
                </div>
            </section>

            <!-- 11. PREGUNTAS FRECUENTES (FAQ Acordeón interactivo en Alpine.js) -->
            <section class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24" id="preguntas-frecuentes">
                <div class="flex flex-col items-center gap-10">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Dudas resueltas
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Preguntas frecuentes
                        </h2>
                        <p class="text-base text-slate-600">
                            Todo lo que necesitas saber sobre MiCatalogo antes de empezar.
                        </p>
                    </div>

                    <div class="w-full flex flex-col gap-3" x-data="{ active: null }">
                        <!-- FAQ 1 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 1 ? null : 1)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿MiCatalogo es gratis?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 1 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 1" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Sí. Puedes crear tu catálogo y empezar a mostrar tus productos gratuitamente sin ningún costo oculto ni comisiones por ventas.
                            </div>
                        </div>

                        <!-- FAQ 2 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 2 ? null : 2)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿Necesito una página web o dominio propio?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 2 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 2" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                No. MiCatalogo te proporciona un enlace directo (por ejemplo, micatalogo.bsolutions.dev/tienda/tu-negocio) que puedes compartir inmediatamente sin pagar hosting ni comprar dominios.
                            </div>
                        </div>

                        <!-- FAQ 3 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 3 ? null : 3)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿Puedo compartirlo por WhatsApp?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 3 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 3" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Sí, totalmente. Puedes copiar tu enlace y enviarlo por chats individuales de WhatsApp, pegarlo en tus estados, incluirlo en tu biografía de Instagram o enviarlo por cualquier red social.
                            </div>
                        </div>

                        <!-- FAQ 4 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 4 ? null : 4)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿Mis clientes tienen que registrarse o descargar una aplicación?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 4 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 4" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                No. Tus clientes abren tu catálogo directamente en cualquier navegador web móvil o de escritorio sin tener que instalar aplicaciones ni crear cuentas.
                            </div>
                        </div>

                        <!-- FAQ 5 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 5 ? null : 5)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿Mis clientes verán otras tiendas o competencia?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 5 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 5" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                No. Cuando un cliente entra mediante tu enlace, se encuentra en tu vitrina exclusiva. No existe un directorio global de tiendas ni recomendaciones de vendedores competidores.
                            </div>
                        </div>

                        <!-- FAQ 6 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 6 ? null : 6)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿Puedo actualizar mis productos cuando quiera?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 6 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 6" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Sí. Puedes cambiar precios, agregar fotos, pausar artículos o marcar existencias en cualquier instante sin que cambie el enlace de tu tienda.
                            </div>
                        </div>

                        <!-- FAQ 7 -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs cursor-pointer select-none transition-colors"
                             @click="active = (active === 7 ? null : 7)">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">¿MiCatalogo procesa los pagos o cobra comisiones?</h3>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180 text-blue-600': active === 7 }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                            <div x-show="active === 7" x-cloak x-collapse class="mt-3 pt-3 border-t border-slate-100 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                No. MiCatalogo funciona como tu vitrina digital propia. La coordinación de pago, entrega y cobro se realiza directamente entre tú y tu cliente por WhatsApp, como siempre lo has hecho.
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 12. CTA FINAL DE ALTO IMPACTO (Royal Blue Banner) -->
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 lg:pb-16">
                <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-700 text-white rounded-3xl p-8 sm:p-14 lg:p-20 shadow-2xl flex flex-col items-center text-center gap-5 relative overflow-hidden">
                    <div class="w-14 h-14 rounded-2xl bg-white/15 text-white flex items-center justify-center shadow-md backdrop-blur-md">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>

                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold max-w-3xl leading-tight tracking-tight">
                        Tus productos ya están en tu teléfono. Ponlos todos en un solo lugar.
                    </h2>

                    <p class="text-base sm:text-lg text-blue-100 max-w-2xl leading-relaxed">
                        Empieza hoy. Crece cuando lo necesites. Organiza tu negocio y comparte tu catálogo con tus clientes.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                        @guest
                            <a class="inline-flex items-center justify-center gap-2 bg-white text-blue-700 hover:bg-blue-50 font-bold text-base px-8 py-4 rounded-xl shadow-xl transition-all active:scale-98 group" href="{{ route('register') }}">
                                <span>Crear mi catálogo gratis</span>
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        @else
                            <a class="inline-flex items-center justify-center gap-2 bg-white text-blue-700 hover:bg-blue-50 font-bold text-base px-8 py-4 rounded-xl shadow-xl transition-all active:scale-98 group" href="{{ route('seller.dashboard') }}">
                                <span>Ir a mi panel de tiendas</span>
                                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        @endguest
                    </div>

                    <span class="text-xs text-blue-200 mt-1 font-medium">
                        Gratis • Sin tarjeta de crédito • Configuración en 3 minutos
                    </span>
                </div>
            </section>

            <!-- Publicidad Slot -->
            <x-ad-slot position="home_bottom" class="max-w-4xl mx-auto px-4 pb-12" />
        </main>

        <!-- 13. FOOTER INSTITUCIONAL (Light / White theme) -->
        <footer class="w-full bg-white border-t border-slate-200/90 shadow-2xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-8 pb-10 border-b border-slate-100">
                    <!-- Brand Column -->
                    <div class="lg:col-span-2 flex flex-col gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <span class="text-lg font-extrabold tracking-tight text-slate-900">Mi<span class="text-blue-600">Catalogo</span></span>
                        </div>
                        <p class="text-xs text-slate-500 max-w-sm leading-relaxed">
                            Catálogo, ventas, inventario y comprobantes para tu negocio. Empieza gratis.
                        </p>
                    </div>

                    <!-- Producto -->
                    <div class="flex flex-col gap-2.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Producto</span>
                        <ul class="flex flex-col gap-2 text-xs text-slate-600">
                            <li><a class="hover:text-blue-600 transition-colors" href="#como-funciona">Cómo funciona</a></li>
                            <li><a class="hover:text-blue-600 transition-colors" href="#ventajas">Ventajas</a></li>
                            <li><a class="hover:text-blue-600 transition-colors" href="{{ route('downloads.index') }}">Descarga</a></li>
                            <li><a class="hover:text-blue-600 transition-colors" href="{{ route('register') }}">Crear catálogo</a></li>
                        </ul>
                    </div>

                    <!-- Cuenta -->
                    <div class="flex flex-col gap-2.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Cuenta</span>
                        <ul class="flex flex-col gap-2 text-xs text-slate-600">
                            @guest
                                <li><a class="hover:text-blue-600 transition-colors" href="{{ route('login') }}">Iniciar sesión</a></li>
                                <li><a class="hover:text-blue-600 transition-colors" href="{{ route('register') }}">Registrarme</a></li>
                            @else
                                <li><a class="hover:text-blue-600 transition-colors" href="{{ route('seller.dashboard') }}">Mi panel de tiendas</a></li>
                                <li><a class="hover:text-blue-600 transition-colors" href="{{ route('seller.shops.create') }}">Crear nueva tienda</a></li>
                            @endguest
                        </ul>
                    </div>

                    <!-- Legal -->
                    <div class="flex flex-col gap-2.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Legal</span>
                        <ul class="flex flex-col gap-2 text-xs text-slate-600">
                            <li><a class="hover:text-blue-600 transition-colors" href="#">Términos y condiciones</a></li>
                            <li><a class="hover:text-blue-600 transition-colors" href="#">Política de privacidad</a></li>
                        </ul>
                    </div>

                    <!-- Ayuda & Contacto -->
                    <div class="flex flex-col gap-2.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Contacto</span>
                        <ul class="flex flex-col gap-2 text-xs text-slate-600">
                            <li><a class="hover:text-blue-600 transition-colors" href="mailto:contacto@bsolutions.dev">contacto@bsolutions.dev</a></li>
                            <li>
                                <a class="inline-flex items-center gap-1.5 hover:text-blue-600 transition-colors font-medium" href="https://www.instagram.com/bsolutions.dev?igsh=MWNhMm02Z2NzY2pvaA==" target="_blank" rel="noopener noreferrer">
                                    <span>@bsolutions.dev</span>
                                </a>
                            </li>
                            <li><a class="hover:text-blue-600 transition-colors" href="#preguntas-frecuentes">Preguntas frecuentes</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <p>© {{ now()->year }} MiCatalogo. Sin directorio de tiendas. Desarrollado por <a class="font-bold text-slate-700 hover:text-blue-600" href="https://bsolutions.dev" target="_blank" rel="noopener noreferrer">BSolutions.dev</a></p>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="font-medium text-slate-600">Plataforma segura para comercios locales</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</x-layouts.app>
