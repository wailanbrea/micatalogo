<x-layouts.app title="MiCatalogo | Crea tu catálogo gratis y vende por WhatsApp">
    <div class="min-h-screen bg-[#F8FAFC] text-slate-900 font-sans relative overflow-x-hidden selection:bg-blue-600 selection:text-white">
        <!-- Ambient background soft luminous lighting (Light / White theme) -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-[600px] w-[600px] rounded-full bg-blue-100/70 blur-[130px]"></div>
        <div class="pointer-events-none absolute top-[750px] -right-40 h-[700px] w-[700px] rounded-full bg-indigo-100/60 blur-[150px]"></div>
        <div class="pointer-events-none absolute top-[2100px] -left-40 h-[600px] w-[600px] rounded-full bg-emerald-100/50 blur-[140px]"></div>
        <div class="pointer-events-none absolute top-[3500px] -right-40 h-[700px] w-[700px] rounded-full bg-blue-100/60 blur-[160px]"></div>

        <!-- Sticky Header -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-xl border-b border-slate-200/80 shadow-xs transition-all">
            <div class="h-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
                <!-- Logo -->
                <a class="flex items-center gap-2.5 group text-decoration-none" href="{{ route('home') }}">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20 group-hover:bg-blue-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <span class="text-xl font-extrabold tracking-tight text-slate-900">
                        Mi<span class="text-blue-600">Catalogo</span>
                    </span>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-7">
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#como-funciona">Cómo funciona</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#ventajas">Ventajas</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#inventario">Inventario</a>
                    <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="#preguntas-frecuentes">Preguntas frecuentes</a>
                </nav>

                <!-- Auth / Guest Actions -->
                <div class="flex items-center gap-3">
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
                        <a class="text-sm font-semibold text-slate-700 hover:text-blue-600 px-3 py-1.5 transition-colors" href="{{ route('login') }}">
                            Iniciar sesión
                        </a>
                        <a class="inline-flex items-center justify-center bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-xl shadow-md shadow-blue-500/20 hover:bg-blue-700 transition-all active:scale-98" href="{{ route('register') }}">
                            Crear catálogo gratis
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
                            Crea tu catálogo gratis.<br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">
                                Sube tus productos una vez.
                            </span><br>
                            Comparte un solo enlace con tus clientes.
                        </h1>

                        <p class="text-lg text-slate-600 max-w-xl leading-relaxed">
                            Deja de publicar las mismas fotos todos los días. Tus clientes exploran tu vitrina por WhatsApp sin intermediarios, sin comisiones y en una <strong class="text-slate-900 font-bold">Vitrina 100% Aislada</strong> sin competidores.
                        </p>

                        <!-- CTA Cluster -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full pt-2">
                            @guest
                                <a class="inline-flex items-center justify-center gap-2 bg-blue-600 text-white font-bold text-base px-7 py-3.5 rounded-xl shadow-lg shadow-blue-600/25 hover:bg-blue-700 transition-all active:scale-98 group" href="{{ route('register') }}">
                                    <span>Crear mi catálogo gratis</span>
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
                                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
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
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 flex items-center justify-center text-3xl shrink-0 overflow-hidden border border-slate-100">
                                        👟
                                    </div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-900 truncate">Nike Air Max 270</span>
                                            <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-md shrink-0">● En stock</span>
                                        </div>
                                        <span class="text-sm font-extrabold text-blue-600 mt-0.5">RD$ 4,500</span>
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
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 flex items-center justify-center text-3xl shrink-0 overflow-hidden border border-slate-100">
                                        👕
                                    </div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-900 truncate">Camisa Polo Slim Fit</span>
                                            <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-md shrink-0">● En stock</span>
                                        </div>
                                        <span class="text-sm font-extrabold text-blue-600 mt-0.5">RD$ 1,200</span>
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
                                    <div class="w-20 h-20 rounded-xl bg-slate-100 flex items-center justify-center text-3xl shrink-0 overflow-hidden border border-slate-100">
                                        🧢
                                    </div>
                                    <div class="flex flex-col flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-xs text-slate-900 truncate">Gorra Urbana Street</span>
                                            <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-md shrink-0">● En stock</span>
                                        </div>
                                        <span class="text-sm font-extrabold text-blue-600 mt-0.5">RD$ 850</span>
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

            <!-- 5. VENTAJAS (Grid de 6 cards) -->
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24" id="ventajas">
                <div class="flex flex-col items-center gap-12">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Todo en uno
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Todo lo que necesitas para mostrar tus productos
                        </h2>
                        <p class="text-base text-slate-600">
                            Diseñado específicamente para las necesidades del vendedor informal y por chat.
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
                            <h3 class="text-lg font-bold text-slate-900">100% Gratis sin comisiones</h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Puedes crear tu catálogo hoy mismo sin suscripciones forzosas, sin intermediarios y sin ingresar tarjeta de crédito.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 6. SECCIÓN INVENTARIO BÁSICO CON SIMULADOR INTERACTIVO -->
            <section class="w-full bg-slate-100/70 border-y border-slate-200/80 py-16 lg:py-24" id="inventario">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                        <div class="lg:col-span-5 flex flex-col gap-5">
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full self-start">
                                Orden y claridad
                            </span>
                            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                                También sabes qué estás vendiendo
                            </h2>
                            <p class="text-base sm:text-lg text-slate-600 leading-relaxed">
                                Registra tus ventas rápidamente y mantén actualizado qué tienes disponible sin complicaciones ni sistemas contables complejos.
                            </p>
                            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                    📱
                                </div>
                                <span class="text-xs sm:text-sm font-semibold text-slate-800">Simple, visual y en tiempo real desde tu propio teléfono.</span>
                            </div>
                        </div>

                        <!-- Simulador Interactivo de Inventario (Alpine.js) -->
                        <div class="lg:col-span-7 flex justify-center"
                             x-data="{ 
                                 stock: 8, 
                                 sales: 12, 
                                 flashSale: false,
                                 sellItem() {
                                     if(this.stock > 0) {
                                         this.stock--;
                                         this.sales++;
                                         this.flashSale = true;
                                         setTimeout(() => this.flashSale = false, 1500);
                                     }
                                 },
                                 restock() {
                                     this.stock += 5;
                                 }
                             }">
                            <div class="w-full max-w-xl bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200 flex flex-col gap-5">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                                            📦
                                        </div>
                                        <span class="text-base font-bold text-slate-900">Control de Stock en Vivo</span>
                                    </div>
                                    <span class="text-xs text-slate-500 font-medium">3 productos registrados</span>
                                </div>

                                <!-- Rows -->
                                <div class="flex flex-col gap-3">
                                    <!-- Fila 1 (Interactiva con Simulación) -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200/80 gap-3 transition-colors"
                                         :class="{ 'bg-emerald-50/80 border-emerald-300 ring-2 ring-emerald-500/20': flashSale }">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-sm text-slate-900">Nike Air Max 270</span>
                                                <span x-show="flashSale" x-cloak class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.2 rounded animate-bounce">+1 Venta!</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-500 text-xs mt-0.5">
                                                <span>Stock: <strong class="text-slate-900 font-bold" x-text="stock">8</strong></span>
                                                <span>•</span>
                                                <span>Vendidos: <strong class="text-slate-900 font-bold" x-text="sales">12</strong></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="sellItem()" class="inline-flex items-center gap-1 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer" title="Prueba registrar una venta">
                                                <span>⚡ +1 Venta</span>
                                            </button>
                                            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold">
                                                Disponible
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Fila 2: Perfume Sauvage -->
                                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-sm text-slate-900">Perfume Sauvage 100ml</span>
                                            <div class="flex items-center gap-2 text-slate-500 text-xs mt-0.5">
                                                <span>Stock: <strong class="text-slate-900 font-bold">2</strong></span>
                                                <span>•</span>
                                                <span>Vendidos: <strong class="text-slate-900 font-bold">20</strong></span>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold flex items-center gap-1">
                                            <span>⚠ Poco stock</span>
                                        </span>
                                    </div>

                                    <!-- Fila 3: Jordan Retro High -->
                                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-sm text-slate-900">Jordan Retro High</span>
                                            <div class="flex items-center gap-2 text-slate-500 text-xs mt-0.5">
                                                <span>Stock: <strong class="text-slate-900 font-bold">0</strong></span>
                                                <span>•</span>
                                                <span>Vendidos: <strong class="text-slate-900 font-bold">15</strong></span>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-800 text-xs font-semibold">
                                            Agotado
                                        </span>
                                    </div>
                                </div>

                                <!-- Resumen Rápido -->
                                <div class="pt-1 flex items-center justify-between text-slate-500 text-xs">
                                    <span>Total ventas registradas: <strong class="text-slate-900" x-text="sales + 35">47 artículos</strong></span>
                                    <span class="text-blue-600 font-semibold">Actualizado hace un instante</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 7. BENEFICIO CLIENTE VS VENDEDOR -->
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
                <div class="flex flex-col items-center gap-12">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Beneficio mutuo
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Más fácil para ti. Más fácil para tus clientes.
                        </h2>
                        <p class="text-base text-slate-600">
                            Optimiza tu tiempo de venta mientras ofreces una experiencia de compra rápida y agradable.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 w-full max-w-5xl">
                        <!-- Para ti (Vendedor) -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-5 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-base border border-blue-100">
                                    💼
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">Para ti (Vendedor)</h3>
                            </div>
                            <div class="flex flex-col gap-3.5 pt-1 text-sm text-slate-700">
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>No buscar las mismas fotos diariamente en la memoria de tu móvil.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Actualizar productos y precios desde un solo panel simplificado.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Compartir siempre el mismo enlace permanente que nunca vence.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Saber con exactitud qué tienes disponible y qué se ha agotado.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Para tus clientes -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-5 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base border border-indigo-100">
                                    👥
                                </div>
                                <h3 class="text-xl font-bold text-slate-900">Para tus clientes</h3>
                            </div>
                            <div class="flex flex-col gap-3.5 pt-1 text-sm text-slate-700">
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Ver todos tus productos ordenados de forma profesional.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Consultar precios transparentes al instante sin preguntar "¿precio?".</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Buscar y filtrar por categorías en cuestión de segundos.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Saber si un artículo está en stock antes de iniciar la conversación.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">✓</span>
                                    <span>Contactarte por WhatsApp con el producto ya seleccionado.</span>
                                </div>
                            </div>
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

            <!-- 9. ANTES VS DESPUÉS -->
            <section class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
                <div class="flex flex-col items-center gap-12">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Cambio radical
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Menos publicar. Más fácil vender.
                        </h2>
                        <p class="text-base text-slate-600">
                            Compara el tiempo que gastas hoy frente al método ordenado de MiCatalogo.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 w-full max-w-5xl">
                        <!-- Antes -->
                        <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-slate-500 font-bold">
                                <span class="text-rose-500 font-extrabold text-lg">✕</span>
                                <h3 class="text-lg font-bold text-slate-900">Antes de MiCatalogo</h3>
                            </div>
                            <div class="flex flex-col gap-3.5 pt-1 text-slate-600 text-sm">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">1</span>
                                    <span>20 fotografías desordenadas en la memoria de tu celular.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">2</span>
                                    <span>Subir una por una a estados y stories de WhatsApp.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">3</span>
                                    <span>24 horas de vigencia y desaparecen para siempre.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">4</span>
                                    <span>Volver a publicar todo desde cero mañana.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Con MiCatalogo -->
                        <div class="bg-white p-8 rounded-3xl border border-blue-200 shadow-md ring-1 ring-blue-500/10 flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-blue-600 font-bold">
                                <span class="text-emerald-500 font-extrabold text-lg">✓</span>
                                <h3 class="text-lg font-bold text-slate-900">Con MiCatalogo</h3>
                            </div>
                            <div class="flex flex-col gap-3.5 pt-1 text-slate-800 text-sm font-medium">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">1</span>
                                    <span>Productos clasificados y organizados por categorías.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">2</span>
                                    <span>Tu catálogo propio con tu identidad y WhatsApp directo.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">3</span>
                                    <span>Un solo enlace permanente disponible 24 horas al día.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">4</span>
                                    <span>Solo actualizas cuando agregues inventario nuevo.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 10. DEMO VISUAL DE VITRINA AMPLIADA -->
            <section class="w-full bg-slate-100/70 border-y border-slate-200/80 py-16 lg:py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center gap-10">
                    <div class="text-center max-w-2xl flex flex-col items-center gap-2">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-widest bg-blue-50 border border-blue-100 px-3 py-1 rounded-full">
                            Demostración interactiva
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                            Así se ve tu catálogo en el teléfono de tu cliente
                        </h2>
                        <p class="text-base text-slate-600">
                            Una vitrina limpia, profesional y ultrarrápida que se adapta a cualquier tamaño de pantalla.
                        </p>
                    </div>

                    <!-- Mockup Ampliado -->
                    <div class="w-full max-w-3xl bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200 flex flex-col gap-5">
                        <!-- Store Header Demo -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-extrabold text-base shadow-xs">
                                    BS
                                </div>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <h3 class="font-bold text-base text-slate-900">BSolutions</h3>
                                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    </div>
                                    <p class="text-xs text-slate-500">Tecnología, accesorios y soluciones exclusivas</p>
                                </div>
                            </div>
                            <div class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-3.5 py-1.5 rounded-xl text-xs font-bold">
                                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                <span>WhatsApp oficial conectado</span>
                            </div>
                        </div>

                        <!-- Search & Category Bar Demo -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1 bg-slate-50 px-3.5 py-2 rounded-xl flex items-center gap-2 border border-slate-200 text-slate-400 text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <span>Buscar productos en BSolutions...</span>
                            </div>
                            <div class="flex items-center gap-1.5 overflow-x-auto text-xs no-scrollbar">
                                <span class="px-3 py-1.5 rounded-xl bg-blue-600 text-white font-semibold">Tenis (14)</span>
                                <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Camisas (8)</span>
                                <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Gorras (5)</span>
                            </div>
                        </div>

                        <!-- Featured Product Item -->
                        <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200/90 flex flex-col sm:flex-row gap-5 items-center">
                            <div class="w-full sm:w-40 h-36 rounded-xl bg-white flex items-center justify-center text-5xl border border-slate-200/80 shrink-0 shadow-2xs">
                                👟
                            </div>
                            <div class="flex flex-col flex-1 w-full gap-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-xs font-bold">
                                        ● En stock para entrega hoy
                                    </span>
                                    <span class="text-xs text-slate-400">Código: BS-109</span>
                                </div>
                                <h4 class="text-base font-bold text-slate-900">Nike Air Max 270 Especial Edition</h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Amortiguación reactiva con cámara de aire visible. Disponibles en tallas del 39 al 43 con entrega a domicilio hoy.
                                </p>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-lg font-black text-blue-600">RD$ 4,500</span>
                                    <span class="inline-flex items-center gap-1.5 bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-xs">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                        Pedir por WhatsApp
                                    </span>
                                </div>
                            </div>
                        </div>
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
                        Crea tu catálogo gratis y empieza a compartir un único enlace con tus clientes hoy mismo.
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
                            Vitrina digital sencilla para vendedores independientes. Comparte tus productos por WhatsApp con un solo enlace sin comisiones.
                        </p>
                    </div>

                    <!-- Producto -->
                    <div class="flex flex-col gap-2.5">
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Producto</span>
                        <ul class="flex flex-col gap-2 text-xs text-slate-600">
                            <li><a class="hover:text-blue-600 transition-colors" href="#como-funciona">Cómo funciona</a></li>
                            <li><a class="hover:text-blue-600 transition-colors" href="#ventajas">Ventajas</a></li>
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
