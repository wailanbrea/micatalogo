@props(['shop', 'active' => 'products'])
@php
    $menus = app(\App\Services\SellerMenuService::class)->visibleForUser($shop, auth()->user());
@endphp
<nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Tipos de catálogo">
    @if (in_array('products', $menus, true))
        <a wire:navigate href="{{ route('seller.shops.products.index', $shop) }}" @if ($active === 'products') aria-current="page" @endif class="rounded-full px-4 py-2 text-sm font-bold {{ $active === 'products' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100' }}">Productos y combos</a>
    @endif
    @if (in_array('services', $menus, true))
        <a wire:navigate href="{{ route('seller.shops.feature', [$shop, 'feature' => 'services']) }}" @if ($active === 'services') aria-current="page" @endif class="rounded-full px-4 py-2 text-sm font-bold {{ $active === 'services' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100' }}">Servicios</a>
    @endif
</nav>
