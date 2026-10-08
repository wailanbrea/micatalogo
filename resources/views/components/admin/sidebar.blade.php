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
    $isViewAllShops = request()->routeIs('seller.dashboard') && request('view') === 'all';
    $isProducts = request()->routeIs('seller.shops.products.*');
    $isInventory = request()->routeIs('seller.shops.inventory.*');
    $isPos = request()->routeIs('seller.shops.pos');
    $isLowStock = $isInventory && request('stock') === 'low';
    $isOutOfStock = $isInventory && request('stock') === 'out';
    $isCustomers = request()->routeIs('seller.shops.customers.*');
    $isSellers = request()->routeIs('seller.shops.sellers.*');
    $isMetrics = request()->routeIs('seller.shops.metrics.*');
    $isSettings = request()->routeIs('seller.shops.edit');
    $isStorefront = request()->routeIs('seller.shops.storefront');
    $isPricing = request()->routeIs('seller.shops.pricing.*');
    $isLots = request()->routeIs('seller.shops.inventory.lots');
    $isCategories = request()->routeIs('seller.shops.categories.*');
    $isImport = request()->routeIs('seller.shops.products.import.*');
    $isBulkImport = request()->routeIs('seller.shops.products.bulk.*');
    $linkClass = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition duration-200';
    $activeClass = 'bg-blue-600 text-white shadow-lg shadow-blue-950/20';
    $idleClass = 'text-slate-300 hover:bg-white/10 hover:text-white';
    $visibleMenus = $contextShop ? app(\App\Services\SellerMenuService::class)->visibleForUser($contextShop, $user) : [];
    $canSeeMenu = fn (string $key): bool => ! $contextShop || in_array($key, $visibleMenus, true);
    $isAssignedSellerOnly = $user?->isAssignedSellerOnly() ?? false;
    $canManageShops = ! $isAssignedSellerOnly;
    $canCreateShop = $isAdmin || ! ($user?->hasActiveShopAssignment() ?? false);
    $featureUrl = fn (string $feature): string => route('seller.shops.feature', ['shop' => $contextShop, 'feature' => $feature]);
    $featureActive = fn (string $feature): bool => request()->routeIs('seller.shops.feature') && request('feature') === $feature;
    $iconPath = 'M4 5h16v14H4zM8 9h8M8 13h5';
    $menuSections = $contextShop ? [
        ['label' => 'Operación', 'items' => [
            ['key' => 'summary', 'label' => 'Resumen', 'url' => route('seller.shops.business', $contextShop), 'active' => request()->routeIs('seller.shops.business')],
            ['key' => 'sales', 'label' => 'Terminal', 'url' => route('seller.shops.pos', $contextShop), 'active' => $isPos, 'path' => 'M3 5h18v14H3zM7 9h4m-4 4h2m5-4h3m-3 4h3'],
            ['key' => 'sales', 'label' => 'Ventas', 'url' => $featureUrl('sales'), 'active' => $featureActive('sales')],
            ['key' => 'quotes', 'label' => 'Cotizaciones', 'url' => $featureUrl('quotes'), 'active' => $featureActive('quotes')],
            ['key' => 'orders', 'label' => 'Pedidos', 'url' => $featureUrl('orders'), 'active' => $featureActive('orders')],
            ['key' => 'encargos', 'label' => 'Encargos', 'url' => $featureUrl('encargos'), 'active' => $featureActive('encargos')],
            ['key' => 'shipments', 'label' => 'Envíos', 'url' => $featureUrl('shipments'), 'active' => $featureActive('shipments')],
            ['key' => 'day_close', 'label' => 'Cierre de día', 'url' => $featureUrl('day_close'), 'active' => $featureActive('day_close')],
            ['key' => 'downloads', 'label' => 'Descargar la app', 'url' => route('downloads.index'), 'active' => request()->routeIs('downloads.*')],
        ]],
        ['label' => 'Compras', 'items' => [
            ['key' => 'containers', 'label' => 'Contenedores', 'url' => $featureUrl('containers'), 'active' => $featureActive('containers')],
            ['key' => 'loads', 'label' => 'Cargas', 'url' => $featureUrl('loads'), 'active' => $featureActive('loads')],
            ['key' => 'suppliers', 'label' => 'Suplidores', 'url' => $featureUrl('suppliers'), 'active' => $featureActive('suppliers')],
            ['key' => 'purchase_invoices', 'label' => 'Facturas', 'url' => $featureUrl('purchase_invoices'), 'active' => $featureActive('purchase_invoices')],
        ]],
        ['label' => 'Catálogo', 'items' => [
            ['key' => 'inventory', 'label' => 'Inventario', 'url' => route('seller.shops.inventory.index', $contextShop), 'active' => $isInventory && ! $isLots, 'path' => 'M4 7h16v13H4zM8 3h8v4H8zM8 12h8'],
            ['key' => 'photos', 'label' => 'Fotos', 'url' => $featureUrl('photos'), 'active' => $featureActive('photos')],
            ['key' => 'storefront', 'label' => 'Mi tienda', 'url' => route('seller.shops.storefront', $contextShop), 'active' => $isStorefront],
            ['key' => 'services', 'label' => 'Servicios', 'url' => $featureUrl('services'), 'active' => $featureActive('services')],
            ['key' => 'price_health', 'label' => 'Salud de precios', 'url' => $featureUrl('price_health'), 'active' => $featureActive('price_health')],
            ['key' => 'products', 'label' => 'Precios automáticos', 'url' => route('seller.shops.pricing.index', $contextShop), 'active' => $isPricing],
            ['key' => 'decants', 'label' => 'Decants', 'url' => $featureUrl('decants'), 'active' => $featureActive('decants')],
            ['key' => 'attributes', 'label' => 'Marcas y atributos', 'url' => $featureUrl('attributes'), 'active' => $featureActive('attributes')],
            ['key' => 'products', 'label' => 'Importar', 'url' => route('seller.shops.products.import.create', $contextShop), 'active' => $isImport],
        ]],
        ['label' => 'Cobros', 'items' => [
            ['key' => 'credit', 'label' => 'Crédito', 'url' => $featureUrl('credit'), 'active' => $featureActive('credit')],
            ['key' => 'customers', 'label' => 'Clientes', 'url' => route('seller.shops.customers.index', $contextShop), 'active' => $isCustomers],
        ]],
        ['label' => 'Finanzas', 'items' => [
            ['key' => 'finance', 'label' => 'Ganancias', 'url' => route('seller.shops.business', $contextShop), 'active' => request()->routeIs('seller.shops.business')],
            ['key' => 'inventory_adjustments', 'label' => 'Ajustes de inventario', 'url' => $featureUrl('inventory_adjustments'), 'active' => $featureActive('inventory_adjustments')],
            ['key' => 'expenses', 'label' => 'Gastos', 'url' => route('seller.shops.expenses.index', $contextShop), 'active' => request()->routeIs('seller.shops.expenses.*')],
            ['key' => 'partners', 'label' => 'Socios', 'url' => $featureUrl('partners'), 'active' => $featureActive('partners')],
        ]],
        ['label' => 'Análisis', 'items' => [
            ['key' => 'reports', 'label' => 'Reportes', 'url' => $featureUrl('reports'), 'active' => $featureActive('reports')],
        ]],
        ['label' => 'Equipo', 'items' => [
            ['key' => 'commissions', 'label' => 'Comisiones', 'url' => $featureUrl('commissions'), 'active' => $featureActive('commissions')],
            ['key' => 'authorizations', 'label' => 'Autorizaciones', 'url' => $featureUrl('authorizations'), 'active' => $featureActive('authorizations')],
        ]],
        ['label' => 'Ajustes', 'items' => [
            ['key' => 'shop_settings', 'label' => 'Configuración', 'url' => route('seller.shops.edit', $contextShop), 'active' => $isSettings],
            ['key' => 'sellers', 'label' => 'Equipo', 'aria' => 'Vendedores', 'url' => route('seller.shops.sellers.index', $contextShop), 'active' => $isSellers],
            ['key' => 'accountant', 'label' => 'Contador', 'url' => $featureUrl('accountant'), 'active' => $featureActive('accountant')],
            ['key' => 'account', 'label' => 'Mi cuenta', 'url' => $featureUrl('account'), 'active' => $featureActive('account')],
            ['key' => 'updates', 'label' => 'Novedades', 'url' => $featureUrl('updates'), 'active' => $featureActive('updates')],
            ['key' => 'help', 'label' => 'Ayuda', 'url' => $featureUrl('help'), 'active' => $featureActive('help')],
            ['key' => 'practice', 'label' => 'Practicar sin miedo', 'url' => $featureUrl('practice'), 'active' => $featureActive('practice')],
            ['key' => 'support', 'label' => 'Soporte', 'url' => $featureUrl('support'), 'active' => $featureActive('support')],
        ]],
        ['label' => 'Herramientas MiCatalogo', 'items' => [
            ['key' => 'cash', 'label' => 'Control de caja', 'url' => route('seller.shops.cash.index', $contextShop), 'active' => request()->routeIs('seller.shops.cash.*')],
            ['key' => 'metrics', 'label' => 'Métricas y QR', 'url' => route('seller.shops.metrics.index', $contextShop), 'active' => $isMetrics],
            ['key' => 'public_catalog', 'label' => 'Compartir catálogo', 'url' => route('shops.show', $contextShop), 'active' => false, 'external' => true],
            ['key' => 'products', 'label' => 'Productos', 'url' => route('seller.shops.products.index', $contextShop), 'active' => $isProducts],
            ['key' => 'products', 'label' => 'Categorías', 'url' => route('seller.shops.categories.index', $contextShop), 'active' => $isCategories],
            ['key' => 'products', 'label' => 'Subida masiva', 'url' => route('seller.shops.products.bulk.create', $contextShop), 'active' => $isBulkImport],
            ['key' => 'inventory', 'label' => 'Lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $contextShop), 'active' => $isLots],
        ]],
    ] : [];
@endphp

<aside class="panel-sidebar fixed inset-y-0 left-0 z-40 hidden w-80 flex-col border-r border-slate-800 text-white md:flex" aria-label="Navegación del panel">
    <div class="border-b border-white/10 px-5 py-5">
        <a wire:navigate.hover class="flex items-center gap-3" href="{{ $contextShop ? route('seller.shops.products.index', $contextShop) : route('seller.dashboard') }}">
            @if ($contextShop?->logo_url)
                <img src="{{ $contextShop->logo_url }}" alt="{{ $contextShop->name }}" class="h-14 w-14 rounded-full border border-slate-200 object-cover shadow-sm">
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-lg font-black text-white shadow-lg shadow-blue-950/30">
                    {{ strtoupper(substr($contextShop?->name ?? 'M', 0, 1)) }}
                </span>
            @endif
            <span class="min-w-0">
                <span class="block truncate text-[15px] font-black tracking-tight text-white">{{ $contextShop?->name ?? 'MiCatalogo' }}</span>
                <span class="mt-0.5 block truncate text-[11px] font-medium text-stone-400">{{ $contextShop ? 'Tu catálogo, siempre contigo' : 'Tu negocio, siempre contigo' }}</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Navegación principal">
        @if ($contextShop)
            @foreach ($menuSections as $section)
                <p class="{{ $loop->first ? 'px-3 pb-2' : 'mt-5 px-3 pb-2 pt-2' }} text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ $section['label'] }}</p>
                @foreach ($section['items'] as $item)
                    @if (in_array($item['key'], ['summary', 'downloads'], true) || $canSeeMenu($item['key']))
                        <a wire:navigate.hover href="{{ $item['url'] }}" @if ($item['external'] ?? false) target="_blank" @endif @if ($item['aria'] ?? false) aria-label="{{ $item['aria'] }}" @endif class="{{ $linkClass }} {{ ($item['active'] ?? false) ? $activeClass : $idleClass }}" @if ($item['label'] === 'Contenedores') title="Agrupa compras y recepciones grandes" @endif>
                            <svg class="h-4 w-4 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['path'] ?? $iconPath }}"/></svg>
                            <span class="truncate">{{ $item['label'] }}</span>
                            @if ($item['label'] === 'Productos')<span class="ml-auto rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-bold text-stone-200">{{ $productCount }}</span>@endif
                        </a>
                    @endif
                @endforeach
            @endforeach
            <a href="{{ route('shops.show', $contextShop) }}" target="_blank" class="{{ $linkClass }} {{ $idleClass }}">
                <svg class="h-4 w-4 shrink-0 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.7 13.3 6.6-6.6m-7.1 2.1-2.8 2.8a3 3 0 1 0 4.2 4.2l2.8-2.8m3.6-2 2.8-2.8a3 3 0 1 0-4.2-4.2l-2.8 2.8"/></svg>
                <span>Ver tienda</span>
            </a>
        @else
            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-stone-500">Operación</p>
            @if ($canManageShops)<a wire:navigate.hover href="{{ route('seller.dashboard') }}" class="{{ $linkClass }} {{ $idleClass }}">Mis tiendas</a>@endif
            @if ($canCreateShop)<a wire:navigate.hover href="{{ route('seller.shops.create') }}" class="{{ $linkClass }} {{ $idleClass }}">Crear tienda</a>@endif
        @endif

        <div class="hidden">
        @if (false)
        <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-stone-500">Operación</p>
        <a wire:navigate.hover href="{{ $isAdmin ? route('admin.dashboard') : route('seller.dashboard') }}" class="{{ $linkClass }} {{ $isDashboard ? $activeClass : $idleClass }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 12 9-9 9 9M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10M9 21v-6h6v6"/></svg>
            <span>Dashboard</span>
        </a>

        @if ($contextShop)
            @if ($canSeeMenu('sales'))
            <a wire:navigate.hover href="{{ route('seller.shops.pos', $contextShop) }}" class="{{ $linkClass }} {{ $isPos ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18v14H3zM7 9h4m-4 4h2m5-4h3m-3 4h3M7 17h10"/></svg>
                <span>Punto de venta</span>
            </a>
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', $contextShop) }}" class="{{ $linkClass }} {{ $isInventory && ! $isLowStock && ! $isOutOfStock ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16v13H4zM8 3h8v4H8zM8 12h8"/></svg>
                <span>Inventario</span>
            </a>
            @endif
            @if ($canSeeMenu('products') || $canSeeMenu('inventory'))
                <p class="mt-5 px-3 pb-2 pt-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Catálogo</p>
            @endif
            @if ($canSeeMenu('products'))
            <a wire:navigate.hover href="{{ route('seller.shops.products.index', $contextShop) }}" class="{{ $linkClass }} {{ $isProducts ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m20 7-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span>Productos</span>
                <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ $productCount }}</span>
            </a>
            <a wire:navigate.hover href="{{ route('seller.shops.categories.index', $contextShop) }}" class="{{ $linkClass }} {{ $isCategories ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10"/></svg>
                <span>Categorías</span>
            </a>
            @if ($isAdmin || $user->ownsShop($contextShop))
                <a wire:navigate.hover href="{{ route('seller.shops.products.import.create', $contextShop) }}" class="{{ $linkClass }} {{ $isImport ? $activeClass : $idleClass }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
                    <span>Importar inventario</span>
                </a>
                <a wire:navigate.hover href="{{ route('seller.shops.products.bulk.create', $contextShop) }}" class="{{ $linkClass }} {{ $isBulkImport ? $activeClass : $idleClass }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16v14H4zM8 9h8m-8 4h5"/></svg>
                    <span>Subida masiva</span>
                </a>
            @endif
            @endif
            @if ($canSeeMenu('inventory') && $canSeeMenu('sales'))
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', [$contextShop, 'stock' => 'low']) }}" class="{{ $linkClass }} {{ $isLowStock ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3Z"/></svg>
                <span>Stock bajo</span>
            </a>
            <a wire:navigate.hover href="{{ route('seller.shops.inventory.index', [$contextShop, 'stock' => 'out']) }}" class="{{ $linkClass }} {{ $isOutOfStock ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9l-6 6m0-6 6 6m6 3a9 9 0 1 1 0-12.73A9 9 0 0 1 21 18Z"/></svg>
                <span>Agotados</span>
            </a>
            @endif
            @if (($isAdmin || $user->ownsShop($contextShop)) && $canSeeMenu('inventory'))
                <a wire:navigate.hover href="{{ route('seller.shops.inventory.lots', $contextShop) }}" class="{{ $linkClass }} {{ $isLots ? $activeClass : $idleClass }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5h14v14H5zM8 9h8m-8 3h5m-5 3h8"/></svg>
                    <span>Lotes y costos FIFO</span>
                </a>
            @endif
            @if (($isAdmin || $user->ownsShop($contextShop)) && $canSeeMenu('products'))
                <a wire:navigate.hover href="{{ route('seller.shops.pricing.index', $contextShop) }}" class="{{ $linkClass }} {{ $isPricing ? $activeClass : $idleClass }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h8"/></svg>
                    <span>Precios automáticos</span>
                </a>
            @endif
            @if ($canSeeMenu('customers'))
                <p class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Cobros</p>
                <a wire:navigate.hover href="{{ route('seller.shops.customers.index', $contextShop) }}" class="{{ $linkClass }} {{ $isCustomers ? $activeClass : $idleClass }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 0 0-4-4h-1m-4 6H3v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2Zm-3-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                    <span>Clientes y cobros</span>
                </a>
            @endif
            @if ($canSeeMenu('cash') || $canSeeMenu('expenses') || ($canSeeMenu('sales') && $canSeeMenu('finance')))
                <p class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Finanzas</p>
            @endif
            @if ($canSeeMenu('sales') && $canSeeMenu('finance'))
                <a wire:navigate.hover href="{{ route('seller.shops.business', $contextShop) }}" class="{{ $linkClass }} {{ request()->routeIs('seller.shops.business') ? $activeClass : $idleClass }}">
                    <span aria-hidden="true" class="w-5 text-center">↗</span><span>Ganancias y resumen</span>
                </a>
            @endif
            @if ($canSeeMenu('cash'))
            <a wire:navigate.hover href="{{ route('seller.shops.cash.index', $contextShop) }}" class="{{ $linkClass }} {{ request()->routeIs('seller.shops.cash.*') ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2m2 4h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm7-5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>
                <span>Control de caja</span>
            </a>
            @endif
            @if ($canSeeMenu('expenses'))
            <a wire:navigate.hover href="{{ route('seller.shops.expenses.index', $contextShop) }}" class="{{ $linkClass }} {{ request()->routeIs('seller.shops.expenses.*') ? $activeClass : $idleClass }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                <span>Gastos operativos</span>
            </a>
            @endif
            @if ($canSeeMenu('metrics'))
            <p class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Análisis</p>
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
            @if ($canSeeMenu('shop_settings') || (($isAdmin || $user->ownsShop($contextShop)) && $canSeeMenu('sellers')))
            <p class="mt-5 border-t border-white/10 px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Equipo y ajustes</p>
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

        @endif
        </div>

        @if ($isAdmin)
            <div class="mt-6 border-t border-blue-400/30 pt-4" aria-label="Menú Administrativo">
                <p class="mx-3 border-b-2 border-blue-500 px-0 pb-2 text-[10px] font-black uppercase tracking-[0.16em] text-blue-300">Menú Administrativo</p>
                <a wire:navigate.hover href="{{ route('admin.dashboard') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.dashboard') ? $activeClass : $idleClass }}">Panel administrativo</a>
                <a wire:navigate.hover href="{{ route('seller.dashboard', ['view' => 'all']) }}" class="{{ $linkClass }} {{ $isViewAllShops ? $activeClass : $idleClass }}">Todas las tiendas</a>
                <a wire:navigate.hover href="{{ route('admin.categories.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.categories.*') ? $activeClass : $idleClass }}">Categorías globales</a>
                <a wire:navigate.hover href="{{ route('admin.reports.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.reports.*') ? $activeClass : $idleClass }}">Reportes</a>
                <a wire:navigate.hover href="{{ route('admin.support.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.support.*') ? $activeClass : $idleClass }}">Soporte</a>
                <a wire:navigate.hover href="{{ route('admin.users.index') }}" class="{{ $linkClass }} {{ request()->routeIs('admin.users.*') ? $activeClass : $idleClass }}">Usuarios del sistema</a>
            </div>
        @endif
    </nav>

    <div class="border-t border-white/10 p-3">
        @if ($contextShop)
            <a wire:navigate.hover href="{{ route('seller.shops.edit', $contextShop) }}" class="mb-3 flex items-center gap-3 rounded-2xl bg-white/10 p-3 text-white transition hover:bg-white/15">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-lg text-white shadow-sm">&#9733;</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-black">MiCatalogo {{ $contextShop->planLabel() }}</span>
                    <span class="block text-xs text-slate-400">{{ $productCount }} de {{ $contextShop->productLimit() }} productos</span>
                </span>
                <span class="text-xl text-blue-600">&#8250;</span>
            </a>
        @endif
        <div class="flex items-center gap-3 px-2 py-2">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-sm font-black text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-bold text-white">{{ $user->name }}</span>
                <span class="block text-xs text-slate-400">{{ $isAdmin ? 'Administrador' : 'Vendedor' }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-500/20 hover:text-rose-300" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m17 16 4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1"/></svg>
                </button>
            </form>
        </div>
    </div>
</aside>
