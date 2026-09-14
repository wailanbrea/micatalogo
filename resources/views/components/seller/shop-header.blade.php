@props([
    'shop',
    'activeTab' => 'products',
])

<!-- Store Workspace Context Header & Local Navigation Tabs -->
<div class="mb-6 rounded-xl border border-slate-200 bg-white shadow-xs">
    <!-- Store Identity Row -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-4 sm:px-6">
        <div class="space-y-0.5">
            <div class="flex items-center gap-2.5">
                @if ($shop->logo_url)
                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-8 w-8 rounded-lg object-cover border border-slate-200 shadow-2xs shrink-0">
                @else
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 font-black text-blue-700 text-sm shrink-0">
                        {{ strtoupper(substr($shop->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h2 class="text-base font-bold text-slate-900 leading-tight">{{ $shop->name }}</h2>
                    <p class="text-xs text-slate-500 font-mono">
                        /tienda/{{ $shop->slug }} · +{{ $shop->whatsapp_country_code }} {{ $shop->whatsapp_number }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a 
                href="{{ route('shops.show', $shop) }}" 
                target="_blank" 
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition"
            >
                <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>Ver vitrina ↗</span>
            </a>
        </div>
    </div>

    <!-- Local Navigation Tabs -->
    <nav class="flex items-center gap-1 overflow-x-auto whitespace-nowrap px-4 sm:px-6 text-xs font-semibold" aria-label="Navegación de la tienda">
        <!-- Productos -->
        <a 
            href="{{ route('seller.shops.products.index', $shop) }}" 
            class="flex items-center gap-1.5 py-3 px-3 border-b-2 transition {{ $activeTab === 'products' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            <svg class="h-4 w-4 shrink-0 {{ $activeTab === 'products' ? 'text-blue-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span>Productos</span>
            <span class="rounded-full bg-slate-100 px-1.5 py-0.2 text-[10px] text-slate-600 font-mono">
                {{ $shop->products()->count() }}
            </span>
        </a>

        <!-- Inventario Lite -->
        <a
            href="{{ route('seller.shops.inventory.index', $shop) }}"
            class="flex items-center gap-1.5 py-3 px-3 border-b-2 transition {{ $activeTab === 'inventory' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            <svg class="h-4 w-4 shrink-0 {{ $activeTab === 'inventory' ? 'text-blue-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            <span>Inventario</span>
            @php
                $alertCount = \App\Models\ProductInventory::whereHas('product', fn($q) => $q->where('shop_id', $shop->id))
                    ->where('track_inventory', true)
                    ->where(function($q) {
                        $q->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                          ->orWhere('stock_quantity', '<=', 0);
                    })
                    ->count();
            @endphp
            @if ($alertCount > 0)
                <span class="rounded-full bg-amber-100 px-1.5 py-0.2 text-[10px] font-bold text-amber-800 font-mono">
                    {{ $alertCount }}
                </span>
            @endif
        </a>

        <!-- Categorías -->
        <a 
            href="{{ route('seller.shops.categories.index', $shop) }}" 
            class="flex items-center gap-1.5 py-3 px-3 border-b-2 transition {{ $activeTab === 'categories' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            <svg class="h-4 w-4 shrink-0 {{ $activeTab === 'categories' ? 'text-blue-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            <span>Categorías</span>
        </a>

        <!-- Subida Masiva -->
        <a 
            href="{{ route('seller.shops.products.bulk.create', $shop) }}" 
            class="flex items-center gap-1.5 py-3 px-3 border-b-2 transition {{ $activeTab === 'bulk' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            <svg class="h-4 w-4 shrink-0 {{ $activeTab === 'bulk' ? 'text-blue-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span>Subida masiva</span>
        </a>

        <!-- Métricas y Difusión -->
        <a 
            href="{{ route('seller.shops.metrics.index', $shop) }}" 
            class="flex items-center gap-1.5 py-3 px-3 border-b-2 transition {{ $activeTab === 'metrics' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            <svg class="h-4 w-4 shrink-0 {{ $activeTab === 'metrics' ? 'text-blue-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Métricas & QR</span>
        </a>

        <!-- Configuración -->
        <a 
            href="{{ route('seller.shops.edit', $shop) }}" 
            class="flex items-center gap-1.5 py-3 px-3 border-b-2 transition {{ $activeTab === 'settings' ? 'border-blue-600 text-blue-700 font-bold' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            <svg class="h-4 w-4 shrink-0 {{ $activeTab === 'settings' ? 'text-blue-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Configuración</span>
        </a>
    </nav>
</div>
