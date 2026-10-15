@php
    $user = auth()->user();
    $menus = app(\App\Services\SellerMenuService::class);
    $isAdmin = $user?->isAdmin() ?? false;
    $isPlatformOwner = $user && $menus->isPlatformOwner($user);
    $ownerStoreMode = $isAdmin && (bool) session('owner_store_mode', false);
    $contextShop = $user ? app(\App\Services\SellerNavigationService::class)->contextShop($user, request()->route('shop')) : null;
    $visibleMenus = $contextShop ? $menus->visibleForUser($contextShop, $user) : [];
    $productCount = $contextShop ? $contextShop->products()->count() : 0;
    $canManageShops = ! ($user?->isAssignedSellerOnly() ?? false);
    $canCreateShop = $user && $user->can('create', \App\Models\Shop::class);
    $contextRoleLabel = $ownerStoreMode ? 'Administrador de tienda' : ($isAdmin ? 'Administrador' : (
        $contextShop && $user->ownsShop($contextShop) ? 'Propietario' : (
            $contextShop && $user->isActiveShopManager($contextShop) ? 'Administrador de tienda' : (
                $contextShop && $user->isActiveShopAccountant($contextShop) ? 'Contador' : 'Vendedor'))));
    $linkClass = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
    $activeClass = 'bg-blue-600 text-white shadow-lg shadow-blue-950/20';
    $idleClass = 'text-slate-300 hover:bg-white/10 hover:text-white';
@endphp

<aside class="panel-sidebar fixed inset-y-0 left-0 z-40 hidden w-80 flex-col border-r border-slate-800 text-white lg:flex" aria-label="Navegación del panel">
    <div class="border-b border-white/10 px-5 py-5">
        <a wire:navigate.hover class="flex items-center gap-3" href="{{ $contextShop ? route('seller.shops.summary', $contextShop) : route('seller.dashboard') }}">
            @if ($contextShop?->logo_url)
                <img src="{{ $contextShop->logo_url }}" alt="{{ $contextShop->name }}" class="h-14 w-14 rounded-full border border-slate-200 object-cover shadow-sm">
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-lg font-black text-white shadow-lg shadow-blue-950/30">{{ strtoupper(substr($contextShop?->name ?? 'M', 0, 1)) }}</span>
            @endif
            <span class="min-w-0">
                <span class="block truncate text-[15px] font-black tracking-tight">{{ $contextShop?->name ?? 'MiCatalogo' }}</span>
                <span class="mt-0.5 block truncate text-[11px] font-medium text-stone-400">Tu negocio, siempre contigo</span>
            </span>
        </a>
    </div>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Navegación principal">
        @if ($contextShop)
            <x-admin.navigation-tree :shop="$contextShop" :user="$user" />
        @else
            @if ($canManageShops)<a wire:navigate href="{{ route('seller.dashboard') }}" class="{{ $linkClass }} {{ $idleClass }}">Mis tiendas</a>@endif
            @if ($canCreateShop)<a wire:navigate href="{{ route('seller.shops.create') }}" class="{{ $linkClass }} {{ $idleClass }}">Crear tienda</a>@endif
        @endif
        @if ($isAdmin && ! $ownerStoreMode)
            <div class="mt-6 border-t border-blue-400/30 pt-4" aria-label="Menú Administrativo">
                <p class="mx-3 border-b-2 border-blue-500 pb-2 text-[10px] font-black uppercase tracking-[0.16em] text-blue-300">Menú Administrativo</p>
                @foreach ([
                    ['admin.dashboard', 'Panel administrativo', 'admin.dashboard'],
                    ['seller.dashboard', 'Todas las tiendas', null],
                    ['admin.categories.index', 'Categorías globales', 'admin.categories.*'],
                    ['admin.reports.index', 'Reportes', 'admin.reports.*'],
                    ['admin.support.index', 'Soporte', 'admin.support.*'],
                    ['admin.users.index', 'Usuarios del sistema', 'admin.users.*'],
                ] as [$routeName, $label, $pattern])
                    <a wire:navigate href="{{ route($routeName, $pattern === null ? ['view' => 'all'] : []) }}" class="{{ $linkClass }} {{ ($pattern ? request()->routeIs($pattern) : request('view') === 'all') ? $activeClass : $idleClass }}">{{ $label }}</a>
                @endforeach
                @if ($isPlatformOwner)<a wire:navigate href="{{ route('admin.menus.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.menus.*') ? $activeClass : $idleClass }}">Menús de todas las tiendas</a>@endif
            </div>
        @endif
    </nav>
    <div class="border-t border-white/10 p-3">
        @if ($isAdmin)
            <form method="POST" action="{{ route('seller.panel-mode.update') }}" class="mb-3">
                @csrf
                <input type="hidden" name="mode" value="{{ $ownerStoreMode ? 'admin' : 'store' }}">
                <button type="submit" aria-label="{{ $ownerStoreMode ? 'Mostrar menú administrativo' : 'Activar modo tienda y ocultar menú administrativo' }}" class="w-full rounded-xl border border-blue-400/30 bg-blue-500/10 p-3 text-left text-xs font-bold text-white hover:bg-blue-500/20">{{ $ownerStoreMode ? 'Modo tienda activo — Mostrar menú administrativo' : 'Activar modo tienda — Ocultar control del sistema' }}</button>
            </form>
        @endif
        @if ($contextShop && in_array('shop_settings', $visibleMenus, true))
            <a wire:navigate href="{{ route('seller.shops.edit', $contextShop) }}" class="mb-3 block rounded-xl bg-white/10 p-3 hover:bg-white/15">
                <span class="block text-sm font-black">MiCatalogo {{ $contextShop->planLabel() }}</span>
                <span class="block text-xs text-slate-400">{{ $productCount }} de {{ $contextShop->productLimit() }} productos</span>
            </a>
        @endif
        <div class="flex items-center gap-3 px-2 py-2">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-sm font-black">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $user->name }}</span><span class="block text-xs text-slate-400">{{ $contextRoleLabel }}</span></span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg p-2 text-slate-400 hover:bg-rose-500/20 hover:text-rose-300" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión">↪</button>
            </form>
        </div>
    </div>
</aside>
