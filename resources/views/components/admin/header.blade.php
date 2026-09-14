@props([
    'title' => null,
    'breadcrumbs' => [],
    'backUrl' => null,
    'backLabel' => 'Volver',
])

@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
    $openReportsCount = $isAdmin ? \App\Models\Report::where('status', 'open')->count() : 0;
    
    // Active route detections
    $isViewAllShops = request()->routeIs('seller.dashboard') && request('view') === 'all';
    $isMyShops = (request()->routeIs('seller.dashboard') && request('view') !== 'all') 
        || request()->routeIs('seller.shops.*') 
        || request()->routeIs('seller.products.*') 
        || request()->routeIs('seller.categories.*');
    $isAdminDashboard = request()->routeIs('admin.dashboard');
    $isAdminReports = request()->routeIs('admin.reports.*');
    $isAdminCategories = request()->routeIs('admin.categories.*');
    $isAdminUsers = request()->routeIs('admin.users.*');
@endphp

<!-- Main Unified Topbar -->
<header class="border-b border-slate-200 bg-white sticky top-0 z-30 shadow-2xs">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2.5 sm:px-6">
        <!-- Brand & Nav Links -->
        <div class="flex items-center gap-4 sm:gap-6">
            <a class="flex items-center gap-2 text-base font-black text-slate-900 hover:opacity-90 transition" href="{{ $isAdmin ? route('admin.dashboard') : route('seller.dashboard') }}">
                <span>Mi<span class="text-blue-600">Catalogo</span></span>
                @if ($isAdmin)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[11px] font-bold text-rose-700">Admin</span>
                @else
                    <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[11px] font-bold text-blue-700">Panel</span>
                @endif
            </a>

            <!-- Desktop Navigation Menu -->
            <nav class="hidden md:flex md:items-center md:gap-1 text-xs font-semibold">
                <!-- Mis Tiendas -->
                <a 
                    class="px-3 py-1.5 rounded-lg transition {{ $isMyShops ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}" 
                    href="{{ route('seller.dashboard') }}"
                >
                    Mis tiendas
                </a>

                @if ($isAdmin)
                    <!-- Todas las Tiendas (Admin) -->
                    <a 
                        class="px-3 py-1.5 rounded-lg transition {{ $isViewAllShops ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}" 
                        href="{{ route('seller.dashboard', ['view' => 'all']) }}"
                    >
                        Todas las tiendas
                    </a>

                    <!-- Dashboard Admin -->
                    <a 
                        class="px-3 py-1.5 rounded-lg transition {{ $isAdminDashboard ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}" 
                        href="{{ route('admin.dashboard') }}"
                    >
                        Dashboard Admin
                    </a>

                    <!-- Reportes -->
                    <a 
                        class="px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 {{ $isAdminReports ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}" 
                        href="{{ route('admin.reports.index') }}"
                    >
                        <span>Reportes</span>
                        @if ($openReportsCount > 0)
                            <span class="rounded-full {{ $isAdminReports ? 'bg-white text-rose-600' : 'bg-rose-500 text-white' }} px-1.5 py-0.2 text-[10px] font-bold">
                                {{ $openReportsCount }}
                            </span>
                        @endif
                    </a>

                    <!-- Categorías Globales -->
                    <a 
                        class="px-3 py-1.5 rounded-lg transition {{ $isAdminCategories ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}" 
                        href="{{ route('admin.categories.index') }}"
                    >
                        Categorías globales
                    </a>

                    <!-- Usuarios -->
                    <a 
                        class="px-3 py-1.5 rounded-lg transition {{ $isAdminUsers ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}" 
                        href="{{ route('admin.users.index') }}"
                    >
                        Usuarios
                    </a>
                @endif
            </nav>
        </div>

        <!-- Right Side Actions & User Menu -->
        <div class="flex items-center gap-3">
            <!-- Ver Vitrina Pública -->
            <a 
                class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition" 
                href="{{ route('home') }}" 
                target="_blank" 
                title="Abrir vitrina pública principal en una nueva pestaña"
            >
                <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>Ver vitrina</span>
            </a>

            @if ($user)
                <!-- Amazon-style User Account Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button 
                        @click="open = !open" 
                        @click.outside="open = false" 
                        type="button" 
                        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition cursor-pointer"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white shrink-0">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </span>
                        <div class="hidden sm:flex sm:flex-col leading-tight">
                            <span class="text-[10px] text-slate-400 font-normal">Hola, {{ str($user->name)->explode(' ')->first() }}</span>
                            <span class="text-xs font-bold text-slate-800">Mi Cuenta</span>
                        </div>
                        <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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
                        class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-slate-200 bg-white p-2 text-slate-800 shadow-xl ring-1 ring-black/5 z-50 divide-y divide-slate-100"
                    >
                        <!-- User Info -->
                        <div class="px-3 py-2">
                            <p class="text-xs font-bold text-slate-900 truncate">{{ $user->name }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ $user->email }}</p>
                            <span class="mt-1 inline-flex items-center rounded-full {{ $isAdmin ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-blue-50 text-blue-700 border-blue-200' }} border px-2 py-0.5 text-[10px] font-bold">
                                {{ $isAdmin ? 'Administrador / Owner' : 'Vendedor' }}
                            </span>
                        </div>

                        <!-- Panel Links -->
                        <div class="py-1 text-xs">
                            <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Gestión de Catálogos
                            </div>
                            <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 transition {{ $isMyShops ? 'font-bold text-blue-700 bg-blue-50/50' : '' }}">
                                <svg class="h-4 w-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span>Mis tiendas</span>
                            </a>
                            <a href="{{ route('seller.shops.create') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 transition">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>+ Crear nueva tienda</span>
                            </a>
                            @if ($primaryShop = $user?->shops()->first())
                                <a href="{{ route('seller.shops.metrics.index', $primaryShop) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-indigo-700 hover:bg-indigo-50 transition">
                                    <svg class="h-4 w-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    <span>Métricas & QR</span>
                                </a>
                            @endif
                        </div>

                        @if ($isAdmin)
                            <div class="py-1 text-xs">
                                <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-500">
                                    Administración Global
                                </div>
                                <a href="{{ route('seller.dashboard', ['view' => 'all']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition {{ $isViewAllShops ? 'font-bold text-rose-700 bg-rose-50' : '' }}">
                                    <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                    <span>Todas las tiendas</span>
                                </a>
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition {{ $isAdminDashboard ? 'font-bold text-rose-700 bg-rose-50' : '' }}">
                                    <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    <span>Dashboard Admin</span>
                                </a>
                                <a href="{{ route('admin.reports.index') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition {{ $isAdminReports ? 'font-bold text-rose-700 bg-rose-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>Reportes</span>
                                    </div>
                                    @if ($openReportsCount > 0)
                                        <span class="rounded-full bg-rose-500 px-1.5 py-0.2 text-[10px] font-bold text-white">{{ $openReportsCount }}</span>
                                    @endif
                                </a>
                                <a href="{{ route('admin.categories.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition {{ $isAdminCategories ? 'font-bold text-rose-700 bg-rose-50' : '' }}">
                                    <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    <span>Categorías globales</span>
                                </a>
                                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-rose-50 hover:text-rose-900 transition {{ $isAdminUsers ? 'font-bold text-rose-700 bg-rose-50' : '' }}">
                                    <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    <span>Usuarios del sistema</span>
                                </a>
                            </div>
                        @endif

                        <!-- Public & Logout -->
                        <div class="py-1 text-xs">
                            <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 transition">
                                <svg class="h-4 w-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>Ver vitrina pública</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-rose-600 hover:bg-rose-50 transition cursor-pointer font-medium">
                                    <svg class="h-4 w-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    <span>Cerrar sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Mobile Navigation Sub-bar (Always Visible on Small Screens) -->
    <div class="flex md:hidden border-t border-slate-100 px-3 py-1.5 bg-slate-50 items-center gap-1 overflow-x-auto whitespace-nowrap text-xs font-semibold">
        <a class="px-2.5 py-1 rounded-md transition {{ $isMyShops ? 'bg-blue-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900' }}" href="{{ route('seller.dashboard') }}">
            Mis tiendas
        </a>
        @if ($isAdmin)
            <a class="px-2.5 py-1 rounded-md transition {{ $isViewAllShops ? 'bg-blue-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900' }}" href="{{ route('seller.dashboard', ['view' => 'all']) }}">
                Todas las tiendas
            </a>
            <a class="px-2.5 py-1 rounded-md transition {{ $isAdminDashboard ? 'bg-blue-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900' }}" href="{{ route('admin.dashboard') }}">
                Dashboard
            </a>
            <a class="px-2.5 py-1 rounded-md transition inline-flex items-center gap-1 {{ $isAdminReports ? 'bg-blue-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900' }}" href="{{ route('admin.reports.index') }}">
                Reportes
                @if ($openReportsCount > 0)
                    <span class="rounded-full {{ $isAdminReports ? 'bg-white text-rose-600' : 'bg-rose-500 text-white' }} px-1 text-[10px]">
                        {{ $openReportsCount }}
                    </span>
                @endif
            </a>
            <a class="px-2.5 py-1 rounded-md transition {{ $isAdminCategories ? 'bg-blue-600 text-white font-bold' : 'text-slate-600 hover:text-slate-900' }}" href="{{ route('admin.categories.index') }}">
                Categorías
            </a>
        @endif
        <a class="px-2.5 py-1 rounded-md text-slate-500 hover:text-slate-900" href="{{ route('home') }}" target="_blank">
            Vitrina ↗
        </a>
    </div>

    <!-- Contextual Breadcrumb Wayfinding Bar -->
    @if (!empty($breadcrumbs))
        <div class="border-t border-slate-200/80 bg-slate-50/75 px-4 py-2 sm:px-6">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                <nav class="flex items-center gap-1.5 text-xs text-slate-500 overflow-x-auto whitespace-nowrap py-0.5" aria-label="Ruta de navegación">
                    @foreach ($breadcrumbs as $crumb)
                        @if (!$loop->first)
                            <svg class="h-3 w-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        @endif
                        @if (!empty($crumb['url']) && !$loop->last)
                            <a href="{{ $crumb['url'] }}" class="hover:text-blue-600 transition font-medium">{{ $crumb['label'] }}</a>
                        @else
                            <span class="font-bold text-slate-900" aria-current="page">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            </div>
        </div>
    @endif
</header>

