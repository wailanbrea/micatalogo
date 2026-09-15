<x-layouts.app title="MiCatalogo | Crea tu catálogo digital para vender por WhatsApp">
    <div class="min-h-screen bg-[#0A0F1D] text-slate-100 relative overflow-hidden flex flex-col justify-between">
        <!-- Ambient background lighting effects -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-[500px] w-[500px] rounded-full bg-blue-600/15 blur-[140px]"></div>
        <div class="pointer-events-none absolute top-1/2 -right-40 h-[600px] w-[600px] rounded-full bg-indigo-600/15 blur-[160px]"></div>

        <div>
            <!-- Top Navbar -->
            <header class="border-b border-slate-800/80 bg-slate-950/40 backdrop-blur-md sticky top-0 z-30">
                <div class="mx-auto flex max-w-[1400px] items-center justify-between px-4 py-3.5 sm:px-8">
                    <a class="flex items-center gap-2 text-xl font-black tracking-tight text-white hover:opacity-95 transition" href="{{ route('home') }}">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600 text-white font-extrabold shadow-md shadow-blue-500/30">M</span>
                        <span>Mi<span class="text-blue-400">Catalogo</span></span>
                    </a>

                    <nav class="flex items-center gap-3 text-sm">
                        @auth
                            <!-- Authenticated Account Menu -->
                            <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    class="flex items-center gap-2 rounded-xl border border-slate-700/80 bg-slate-900/80 px-3 py-1.5 text-left text-xs text-white hover:border-slate-600 hover:bg-slate-800 transition focus:outline-none cursor-pointer"
                                    aria-expanded="false"
                                    :aria-expanded="open.toString()"
                                >
                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-xs font-bold text-white shadow-xs">
                                        {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                    </div>
                                    <div class="hidden sm:flex flex-col">
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
                                    class="absolute right-0 mt-2 w-72 origin-top-right rounded-2xl border border-slate-800 bg-slate-900 p-2 text-slate-200 shadow-2xl z-50 divide-y divide-slate-800"
                                >
                                    <div class="px-3 py-2.5">
                                        <p class="text-xs font-bold text-white truncate">{{ auth()->user()->name }}</p>
                                        <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email }}</p>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            @if (auth()->user()->isAdmin())
                                                <span class="inline-flex items-center rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-400 border border-rose-500/20">
                                                    Administrador / Owner
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-400 border border-blue-500/20">
                                                    Vendedor
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="py-1 text-xs">
                                        <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                            <svg class="h-4 w-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                            <span>Panel de tiendas</span>
                                        </a>
                                        <a href="{{ route('seller.shops.create') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                            <svg class="h-4 w-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            <span>Crear nueva tienda</span>
                                        </a>
                                        @if (auth()->user()->isAdmin())
                                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-rose-400 hover:bg-slate-800 hover:text-rose-300 transition">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                <span>Dashboard del Sistema</span>
                                            </a>
                                            <a href="{{ route('admin.categories.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                                <span>Categorías globales</span>
                                            </a>
                                        @endif
                                    </div>

                                    <div class="py-1 text-xs">
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left flex items-center gap-2 rounded-lg px-3 py-2 text-rose-400 hover:bg-slate-800 hover:text-rose-300 transition cursor-pointer">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                                <span>Cerrar sesión</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @else
                            <a class="text-xs font-semibold text-slate-300 hover:text-white transition px-2 py-1.5" href="{{ route('login') }}">
                                Iniciar sesión
                            </a>
                            <a class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shadow-md shadow-blue-500/20" href="{{ route('register') }}">
                                Crear catálogo gratis
                            </a>
                        @endauth
                    </nav>
                </div>
            </header>

            <!-- Hero Section -->
            <section class="relative px-4 pt-16 pb-20 sm:px-8 sm:pt-24 sm:pb-28">
                <div class="mx-auto max-w-4xl text-center">
                    <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1.5 text-xs font-semibold text-blue-400 mb-6">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                        <span>Plataforma de Vitrinas Digitales Independientes</span>
                    </div>

                    <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-6xl sm:leading-[1.15]">
                        Crea tu catálogo gratis.<br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-300 to-sky-400">
                            Sube tus productos una vez.
                        </span><br>
                        Comparte un solo enlace con tus clientes.
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-base sm:text-lg leading-relaxed text-slate-400">
                        Tu negocio merece una vitrina profesional, limpia y sin distracciones. Tus clientes compran directamente en tu WhatsApp sin intermediarios, sin comisiones y <strong class="text-slate-200">sin promocionar a competidores</strong>.
                    </p>

                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        @guest
                            <a class="w-full sm:w-auto rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-base font-extrabold text-white shadow-xl shadow-blue-600/30 hover:from-blue-700 hover:to-indigo-700 active:scale-95 transition" href="{{ route('register') }}">
                                Crear catálogo gratis →
                            </a>
                            <a class="w-full sm:w-auto rounded-2xl border border-slate-700 bg-slate-900/60 px-6 py-4 text-base font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition" href="{{ route('login') }}">
                                Ya tengo cuenta · Iniciar sesión
                            </a>
                        @else
                            <a class="w-full sm:w-auto rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-base font-extrabold text-white shadow-xl shadow-blue-600/30 hover:from-blue-700 hover:to-indigo-700 active:scale-95 transition" href="{{ route('seller.dashboard') }}">
                                Ir a mi panel de tiendas →
                            </a>
                            <a class="w-full sm:w-auto rounded-2xl border border-slate-700 bg-slate-900/60 px-6 py-4 text-base font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition" href="{{ route('seller.shops.create') }}">
                                Crear nueva tienda
                            </a>
                        @endguest
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        ✓ Sin tarjeta de crédito &nbsp;•&nbsp; ✓ Listo en menos de 3 minutos &nbsp;•&nbsp; ✓ Optimizado para celulares
                    </p>
                </div>
            </section>

            <!-- How it works (3 Steps) -->
            <section class="border-t border-slate-800/80 bg-slate-950/40 py-20 px-4 sm:px-8">
                <div class="mx-auto max-w-[1200px]">
                    <div class="text-center mb-14">
                        <span class="text-xs font-bold uppercase tracking-widest text-blue-400">Sencillo y efectivo</span>
                        <h2 class="mt-2 text-2xl sm:text-4xl font-extrabold tracking-tight text-white">¿Cómo funciona MiCatalogo?</h2>
                        <p class="mt-3 text-sm text-slate-400 max-w-xl mx-auto">Digitaliza el inventario de tu negocio en tres pasos sin complicaciones técnicas.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Step 1 -->
                        <div class="relative rounded-2xl border border-slate-800 bg-slate-900/70 p-8 shadow-xl">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600/20 text-blue-400 font-black text-lg border border-blue-500/30 mb-6">
                                1
                            </div>
                            <h3 class="text-lg font-bold text-white">Crea tu cuenta</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                                Regístrate gratis en menos de 1 minuto, indica el nombre de tu tienda y configura tu número oficial de WhatsApp.
                            </p>
                        </div>

                        <!-- Step 2 -->
                        <div class="relative rounded-2xl border border-slate-800 bg-slate-900/70 p-8 shadow-xl">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600/20 text-indigo-400 font-black text-lg border border-indigo-500/30 mb-6">
                                2
                            </div>
                            <h3 class="text-lg font-bold text-white">Sube tus productos</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                                Agrega fotos de alta definición que se optimizan a WebP al instante, define precios en RD$, categorías y existencias.
                            </p>
                        </div>

                        <!-- Step 3 -->
                        <div class="relative rounded-2xl border border-slate-800 bg-slate-900/70 p-8 shadow-xl">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-600/20 text-emerald-400 font-black text-lg border border-emerald-500/30 mb-6">
                                3
                            </div>
                            <h3 class="text-lg font-bold text-white">Comparte tu enlace</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                                Comparte el enlace de tu vitrina en tus redes o estados. Tus clientes abren tu tienda y te contactan con un solo toque.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Platform Guarantees (Total Isolation) -->
            <section class="py-20 px-4 sm:px-8">
                <div class="mx-auto max-w-[1200px]">
                    <div class="text-center mb-14">
                        <span class="text-xs font-bold uppercase tracking-widest text-emerald-400">Diseñado para tu negocio</span>
                        <h2 class="mt-2 text-2xl sm:text-4xl font-extrabold tracking-tight text-white">¿Por qué elegir MiCatalogo?</h2>
                        <p class="mt-3 text-sm text-slate-400 max-w-xl mx-auto">Una herramienta pensada para comerciantes independientes, no un mercado donde compites con otros vendedores.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Guarantee 1 -->
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 mb-4">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <h4 class="text-base font-bold text-white">Vitrina 100% Aislada</h4>
                            <p class="mt-2 text-xs leading-relaxed text-slate-400">
                                Tu cliente solo ve tu catálogo. El buscador, las categorías y las recomendaciones pertenecen únicamente a tu tienda.
                            </p>
                        </div>

                        <!-- Guarantee 2 -->
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-4">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            </div>
                            <h4 class="text-base font-bold text-white">Pedidos por WhatsApp</h4>
                            <p class="mt-2 text-xs leading-relaxed text-slate-400">
                                Cada producto genera un mensaje personalizado directo a tu WhatsApp con el enlace, precio y nombre del artículo.
                            </p>
                        </div>

                        <!-- Guarantee 3 -->
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 mb-4">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <h4 class="text-base font-bold text-white">Carga Instantánea WebP</h4>
                            <p class="mt-2 text-xs leading-relaxed text-slate-400">
                                Conversión automática de imágenes a formato WebP optimizado para una experiencia veloz en cualquier red móvil.
                            </p>
                        </div>

                        <!-- Guarantee 4 -->
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20 mb-4">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <h4 class="text-base font-bold text-white">Control de Inventario</h4>
                            <p class="mt-2 text-xs leading-relaxed text-slate-400">
                                Monitorea stock inicial, ventas realizadas y disponibilidad sin la complejidad de sistemas contables pesados.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Final CTA Banner -->
            <section class="border-t border-slate-800/80 bg-gradient-to-b from-slate-950/60 to-slate-900 py-16 px-4 sm:px-8 text-center">
                <div class="mx-auto max-w-2xl">
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-white">
                        Comienza hoy a vender de forma profesional.
                    </h3>
                    <p class="mt-3 text-sm text-slate-400">
                        Crea tu tienda gratuita y comparte tu vitrina con tus clientes en WhatsApp en cuestión de minutos.
                    </p>
                    <div class="mt-8">
                        <a class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-8 py-3.5 text-sm font-bold text-white shadow-xl shadow-blue-600/30 hover:bg-blue-700 transition" href="{{ route('register') }}">
                            <span>Crear mi catálogo gratis</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                </div>
            </section>

            <x-ad-slot position="home_bottom" class="max-w-4xl mx-auto px-4" />
        </div>

        <x-public-footer />
    </div>
</x-layouts.app>
