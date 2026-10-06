@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
    $activeShop = request()->route('shop');
    $activeShop = $activeShop instanceof \App\Models\Shop ? $activeShop : null;
    $contextShop = $activeShop ?: $user?->shops()->first();
    if (! $contextShop) {
        $contextShop = $user?->shopSellerAssignments()->with('shop')->where('is_active', true)->first()?->shop;
    }
    $productCount = $contextShop ? $contextShop->products()->count() : 0;
    $isDashboard = request()->routeIs('seller.dashboard', 'admin.dashboard');
    $isProducts = request()->routeIs('seller.shops.products.*');
    $isInventory = request()->routeIs('seller.shops.inventory.*');
    $isLowStock = $isInventory && request('stock') === 'low';
    $isOutOfStock = $isInventory && request('stock') === 'out';
    $isCustomers = request()->routeIs('seller.shops.customers.*');
    $isSellers = request()->routeIs('seller.shops.sellers.*');
    $isMetrics = request()->routeIs('seller.shops.metrics.*');
    $isSettings = request()->routeIs('seller.shops.edit');
    $linkClass = 'flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition';
    $activeClass = 'bg-blue-50 text-blue-700 shadow-[inset_4px_0_0_#2563eb]';
    $idleClass = 'text-slate-700 hover:bg-slate-50 hover:text-slate-950';
    $visibleMenus = $contextShop ? app(\App\Services\SellerMenuService::class)->visibleForUser($contextShop, $user) : [];
    $canSeeMenu = fn (string $key): bool => ! $contextShop || in_array($key, $visibleMenus, true);
    $isAssignedSellerOnly = $user?->isAssignedSellerOnly() ?? false;
    $canManageShops = ! $isAssignedSellerOnly;
    $canCreateShop = $isAdmin || ! ($user?->hasActiveShopAssignment() ?? false);
@endphp

<aside class="fixed inset-y-0 left-0 z-40 hidden w-80 flex-col border-r border-slate-200 bg-white md:flex" aria-label="Navegación del panel">
    <div class="border-b border-slate-100 px-5 py-5">
        <a class="flex items-center gap-3" href="{{ $contextShop ? route('seller.shops.products.index', $contextShop) : route('seller.dashboard') }}">
            @if ($contextShop?->logo_url)
                <img src="{{ $contextShop->logo_url }}" alt="{{ $contextShop->name }}" class="h-14 w-14 rounded-full border border-slate-200 object-cover shadow-sm">
            @else
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-950 text-xl font-black text-white shadow-sm">
                    {{ strtoupper(substr($contextShop?->name ?? 'M', 0, 1)) }}
                </span>
            @endif
            <span class="min-w-0">
                <span class="block truncate text-lg font-black tracking-tight text-slate-950">{{ $contextShop?->name ?? 'MiCatalogo' }}</span>
                <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ $contextShop ? 'Tu catálogo, siempre contigo' : 'Tu negocio, siempre contigo' }}</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Navegación principal">
        <a wire:navigate.hover href="{{ $isAdmin ? route('admin.dashboard') : route('seller.dashboard') }}" class="{{ $linkClass }} {{ $isDashboard ? $activeClass : $idleClass }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 12 9-9 9 9M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10M9 21v-6h6v6"/></svg>
            <span>Dashboard</span>
        </a>

        @if ($contextShop)
            @if ($canSeeMenu('sales'))
            <a wire:navigate.hover href="{{ route('seller.shops.business', $contextShop) }}" class="{{ $linkClass }} {{ request()->routeIs('seller.shops.business') ? $activeClass : $idleClass }}">
                <span aria-hidden="true" class="w-5 text-center">↗</span><span>Negocio y ganancias</span>
            </a>
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', $contextShop) }}" class="{{ $linkClass }} {{ $isInventory ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18v14H3zM7 9h4m-4 4h10m-10 4h7"/></svg>
                <span>Vender</span>
            </a>
            @endif
            @if ($canSeeMenu('products'))
            <a wire:navigate.hover href="{{ route('seller.shops.products.index', $contextShop) }}" class="{{ $linkClass }} {{ $isProducts ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m20 7-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span>Productos</span>
                <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $productCount }}</span>
            </a>
            @endif
            @if ($canSeeMenu('inventory'))
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', $contextShop) }}" class="{{ $linkClass }} {{ $isInventory && ! $isLowStock && ! $isOutOfStock ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16v13H4zM8 3h8v4H8zM8 12h8"/></svg>
                <span>Inventario</span>
            </a>
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', [$contextShop, 'stock' => 'low']) }}" class="{{ $linkClass }} {{ $isLowStock ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3Z"/></svg>
                <span>Stock bajo</span>
            </a>
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', [$contextShop, 'stock' => 'out']) }}" class="{{ $linkClass }} {{ $isOutOfStock ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9l-6 6m0-6 6 6m6 3a9 9 0 1 1 0-12.73A9 9 0 0 1 21 18Z"/></svg>
                <span>Agotados</span>
            </a>
            @endif
            @if ($canSeeMenu('cash'))
            <a wire:navigate.hover href="{{ route('seller.shops.cash.index', $contextShop) }}" class="{{ $linkClass }} {{ request()->routeIs('seller.shops.cash.*') ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2m2 4h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm7-5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>
                <span>Control de caja</span>
            </a>
            @endif
            @if ($canSeeMenu('expenses') || $canSeeMenu('sales'))
            <a wire:navigate.hover href="{{ route('seller.shops.expenses.index', $contextShop) }}" class="{{ $linkClass }} {{ request()->routeIs('seller.shops.expenses.*') ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                <span>Gastos operativos</span>
            </a>
            @endif
            @if ($canSeeMenu('customers'))
            <a wire:navigate.hover href="{{ route('seller.shops.customers.index', $contextShop) }}" class="{{ $linkClass }} {{ $isCustomers ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 0 0-4-4h-1m-4 6H3v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2Zm-3-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                <span>Clientes y cobros</span>
            </a>
            @endif
            @if ($canSeeMenu('metrics'))
            <a wire:navigate.hover href="{{ route('seller.shops.metrics.index', $contextShop) }}" class="{{ $linkClass }} {{ $isMetrics ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg>
                <span>Estadísticas</span>
            </a>
            @endif
            @if ($canSeeMenu('public_catalog'))
            <a href="{{ route('shops.show', $contextShop) }}" target="_blank" class="{{ $linkClass }} {{ $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.7 13.3 6.6-6.6m-7.1 2.1-2.8 2.8a3 3 0 1 0 4.2 4.2l2.8-2.8m3.6-2 2.8-2.8a3 3 0 1 0-4.2-4.2l-2.8 2.8"/></svg>
                <span>Compartir catálogo</span>
            </a>
            @endif
            @if ($canSeeMenu('shop_settings'))
            <a wire:navigate.hover href="{{ route('seller.shops.edit', $contextShop) }}" class="{{ $linkClass }} {{ $isSettings ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.3 4.3a1.7 1.7 0 0 1 3.4 0 1.7 1.7 0 0 0 2.6 1.1 1.7 1.7 0 0 1 2.4 2.4 1.7 1.7 0 0 0 1.1 2.6 1.7 1.7 0 0 1 0 3.4 1.7 1.7 0 0 0-1.1 2.6 1.7 1.7 0 0 1-2.4 2.4 1.7 1.7 0 0 0-2.6 1.1 1.7 1.7 0 0 1-3.4 0 1.7 1.7 0 0 0-2.6-1.1 1.7 1.7 0 0 1-2.4-2.4 1.7 1.7 0 0 0-1.1-2.6 1.7 1.7 0 0 1 0-3.4 1.7 1.7 0 0 0 1.1-2.6 1.7 1.7 0 0 1 2.4-2.4 1.7 1.7 0 0 0 2.6-1.1ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                <span>Configuración</span>
            </a>
            @endif
            @if (($isAdmin || $user->ownsShop($contextShop)) && $canSeeMenu('sellers'))
                <a wire:navigate.hover href="{{ route('seller.shops.sellers.index', $contextShop) }}" class="{{ $linkClass }} {{ $isSellers ? $activeClass : $idleClass }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 0 0-4-4h-1m-4 6H3v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2ZM10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                    <span>Vendedores</span>
                </a>
            @endif
        @elseif ($canManageShops)
            <a wire:navigate.hover href="{{ route('seller.dashboard') }}" class="{{ $linkClass }} {{ $idleClass }}">Mis tiendas</a>
            @if ($canCreateShop)<a wire:navigate.hover href="{{ route('seller.shops.create') }}" class="{{ $linkClass }} {{ $idleClass }}">Crear tienda</a>@endif
        @endif

        @if ($isAdmin)
            <div class="mt-5 border-t border-slate-100 pt-4">
                <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-rose-500">Administración</p>
                <a wire:navigate.hover href="{{ route('admin.reports.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.reports.*') ? $activeClass : $idleClass }}">Reportes</a>
                <a wire:navigate.hover href="{{ route('admin.support.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.support.*') ? $activeClass : $idleClass }}">Soporte</a>
                <a wire:navigate.hover href="{{ route('admin.users.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.users.*') ? $activeClass : $idleClass }}">Usuarios</a>
            </div>
        @endif
    </nav>

    <div class="border-t border-slate-100 p-4">
        @if ($contextShop)
            <a wire:navigate.hover href="{{ route('seller.shops.edit', $contextShop) }}" class="mb-3 flex items-center gap-3 rounded-2xl bg-gradient-to-br from-blue-50 to-slate-100 p-3 text-slate-900 transition hover:from-blue-100 hover:to-slate-100">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-lg text-blue-600 shadow-sm">&#9733;</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-black">MiCatalogo {{ $contextShop->planLabel() }}</span>
                    <span class="block text-xs text-slate-500">{{ $productCount }} de {{ $contextShop->productLimit() }} productos</span>
                </span>
                <span class="text-xl text-blue-600">&#8250;</span>
            </a>
        @endif
        <div class="flex items-center gap-3 px-2 py-2">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-black text-slate-700">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-bold text-slate-900">{{ $user->name }}</span>
                <span class="block text-xs text-slate-500">{{ $isAdmin ? 'Administrador' : 'Vendedor' }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m17 16 4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1"/></svg>
                </button>
            </form>
        </div>
    </div>
</aside>
