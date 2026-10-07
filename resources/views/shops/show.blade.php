@php
    $shopHoursService = app(\App\Services\ShopHoursService::class);
    $businessHours = $shopHoursService->isConfigured($shop->business_hours)
        ? $shopHoursService->forForm($shop->business_hours)
        : null;
    $businessHoursStatus = $shopHoursService->currentStatus($shop->business_hours);
    $sharedCartData = $cartItems->map(fn ($cartProduct) => [
        'name' => $cartProduct->name,
        'productId' => $cartProduct->id,
        'price' => $cartProduct->currentPrice(),
        'quantity' => 1,
    ])->values()->all();

    $productsJson = $products->map(fn ($p) => [
        'id' => $p->public_id,
        'name' => $p->name,
        'price' => $p->currentPrice(),
        'image' => $p->images->isNotEmpty() ? $p->images->first()->url : null,
        'url' => route('products.show', [$shop, $p]),
        'isAvailable' => $p->isInventoryTracked()
            ? (!$p->inventory->isOutOfStock() && $p->availability_status->value !== 'out_of_stock')
            : ($p->availability_status->value === 'available'),
        'maxStock' => $p->isInventoryTracked() ? max(1, (int) $p->inventory->stock_quantity) : 9999,
    ])->keyBy('id');

    $activeFiltersCount = ($selectedCategory ? 1 : 0) + ($stock === 'available' ? 1 : 0) + (($minPrice || $maxPrice) ? 1 : 0) + (($sort && $sort !== 'latest') ? 1 : 0) + collect($selectedAttributes)->flatten()->count();
@endphp

<x-layouts.app
    :title="$shop->name.' | Catálogo en MiCatalogo'"
    :description="$shop->description ?: 'Descubre el catálogo de '.$shop->name.' y pide directamente por WhatsApp.'"
    :ogImage="$shop->cover_url ?: $shop->logo_url"
>
    <div 
        x-data="{
            // Cart state (multi-product WhatsApp cart persisted in localStorage)
            cart: [],
            shopId: @js($shop->public_id),
            shopName: @js($shop->name),
            shopWaCode: @js($shop->whatsapp_country_code),
            shopWaNumber: @js($shop->whatsapp_number),
            openCartDrawer: false,
            openFilterDrawer: false,
            customerName: '',
            deliveryType: 'delivery',
            customerNotes: '',
            sendingOrder: false,
            orderError: '',
            
            // Shared cart (legacy POS inventory checkout for authenticated seller)
            selectedProducts: [],
            cartItems: @js($sharedCartData),
            checkoutBusy: false,
            checkoutError: '',

            init() {
                // Load local cart from localStorage
                try {
                    const saved = localStorage.getItem('micatalogo_cart_' + this.shopId);
                    if (saved) {
                        this.cart = JSON.parse(saved);
                    }
                } catch (e) {
                    this.cart = [];
                }

                // Auto-save to localStorage on changes
                this.$watch('cart', (val) => {
                    try {
                        localStorage.setItem('micatalogo_cart_' + this.shopId, JSON.stringify(val));
                    } catch (e) {}
                });
            },

            // Multi-product Cart Methods
            addToCart(item) {
                const existing = this.cart.find(i => i.id === item.id);
                if (existing) {
                    if (existing.quantity < (item.maxStock || 9999)) {
                        existing.quantity++;
                    }
                } else {
                    this.cart.push({
                        id: item.id,
                        name: item.name,
                        price: Number(item.price),
                        image: item.image,
                        url: item.url,
                        quantity: 1,
                        maxStock: item.maxStock || 9999,
                    });
                }
            },
            removeFromCart(productId) {
                this.cart = this.cart.filter(i => i.id !== productId);
            },
            updateCartQuantity(productId, delta) {
                const item = this.cart.find(i => i.id === productId);
                if (!item) return;
                const newQty = item.quantity + delta;
                if (newQty <= 0) {
                    this.removeFromCart(productId);
                } else if (newQty <= item.maxStock) {
                    item.quantity = newQty;
                }
            },
            getItemQuantity(productId) {
                const item = this.cart.find(i => i.id === productId);
                return item ? item.quantity : 0;
            },
            clearCart() {
                this.cart = [];
                try {
                    localStorage.removeItem('micatalogo_cart_' + this.shopId);
                } catch (e) {}
            },
            get cartCount() {
                return this.cart.reduce((total, item) => total + item.quantity, 0);
            },
            get cartTotal() {
                return this.cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
            },
            
            // Build and send WhatsApp Order
            async sendWhatsAppOrder() {
                if (this.cart.length === 0 || this.sendingOrder) return;

                this.sendingOrder = true;
                this.orderError = '';
                const popup = window.open('about:blank', '_blank');

                try {
                    const response = await fetch('{{ route('orders.store', $shop) }}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({
                            items: this.cart.map(item => ({ id: item.id, quantity: item.quantity })),
                            customer_name: this.customerName.trim() || null,
                            delivery_type: this.deliveryType,
                            notes: this.customerNotes.trim() || null,
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload.message || 'No se pudo preparar el pedido. Revisa las existencias.');
                    }

                    this.clearCart();
                    if (popup) {
                        popup.location = payload.whatsapp_url;
                    } else {
                        window.location.href = payload.whatsapp_url;
                    }
                } catch (error) {
                    if (popup) popup.close();
                    this.orderError = error.message;
                    this.sendingOrder = false;
                }
            },

            // Legacy POS / Seller Inventory checkout methods
            toggleProduct(id) {
                this.selectedProducts = this.selectedProducts.includes(id)
                    ? this.selectedProducts.filter((productId) => productId !== id)
                    : [...this.selectedProducts, id];
            },
            get selectedCount() {
                return this.selectedProducts.length;
            },
            get sharedCartTotal() {
                return this.cartItems.reduce((sum, item) => sum + (item.quantity * item.price), 0);
            },
            async checkoutCart() {
                if (this.checkoutBusy) return;
                this.checkoutBusy = true;
                this.checkoutError = '';
                const token = document.querySelector('#shared-cart-form input[name=_token]').value;

                const response = await fetch('{{ route('seller.shops.inventory.checkout', $shop) }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        _token: token,
                        items: this.cartItems.map((item) => ({
                            product_id: item.productId,
                            quantity: Math.max(1, Number(item.quantity) || 1),
                        })),
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.checkoutError = payload.message || 'No se pudo completar el cobro. Revisa el inventario.';
                    this.checkoutBusy = false;
                    return;
                }

                window.location.href = payload.redirect;
            },
            get selectedWhatsAppUrl() {
                const params = new URLSearchParams();
                this.selectedProducts.forEach((id) => params.append('products[]', id));
                return '{{ route('track.wa.shop', $shop) }}?' + params.toString();
            }
        }"
        class="storefront-shell min-h-screen bg-[#F8FAFC] text-slate-800 antialiased"
        style="--shop-primary: {{ $shop->primary_color ?: '#1d4ed8' }}; --shop-secondary: {{ $shop->secondary_color ?: '#0f172a' }};"
    >
        <!-- Top Nav (Compact, Brand-Scoped) -->
        <header class="sticky top-0 z-30 border-b border-slate-200/90 bg-white/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-[1400px] items-center justify-between gap-3 px-4 py-2.5 sm:px-8">
                <!-- Left: Store identity & badge -->
                <div class="flex min-w-0 items-center gap-2.5">
                    @if ($shop->logo_url)
                        <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-8 w-8 shrink-0 rounded-lg object-cover border border-slate-200">
                    @else
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-xs font-black text-white shadow-xs">
                            {{ str($shop->name)->substr(0, 1)->upper() }}
                        </div>
                    @endif
                    <div class="min-w-0 flex items-center gap-2">
                        <span class="truncate font-extrabold text-sm sm:text-base text-slate-900 tracking-tight">{{ $shop->name }}</span>
                        <span class="hidden xs:inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 shrink-0 border border-emerald-200/60">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Vitrina Oficial
                        </span>
                    </div>
                </div>

                <!-- Right: Cart button & User / WhatsApp Actions -->
                <div class="flex shrink-0 items-center gap-2">
                    <!-- WhatsApp Order Cart Pill Trigger (Always visible when items in cart) -->
                    <button 
                        type="button" 
                        @click="openCartDrawer = true" 
                        class="relative inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-2xs hover:bg-slate-50 transition active:scale-95 cursor-pointer"
                        title="Ver mi pedido"
                    >
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span class="hidden sm:inline">Pedido</span>
                        <span 
                            x-show="cartCount > 0" 
                            x-text="cartCount" 
                            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-black text-white"
                        ></span>
                    </button>

                    <!-- Direct WhatsApp Store Link -->
                    <a 
                        class="hidden sm:inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs hover:bg-emerald-700 transition active:scale-95 whitespace-nowrap" 
                        href="{{ route('track.wa.shop', $shop) }}" 
                        data-wa-target="https://wa.me/{{ $shop->whatsapp_country_code.$shop->whatsapp_number }}" 
                        rel="noopener noreferrer" 
                        target="_blank"
                    >
                        <svg class="h-3.5 w-3.5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                        <span>Contactar por WhatsApp</span>
                    </a>

                    <!-- Share Store Button -->
                    <button 
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-slate-700 shadow-2xs hover:bg-slate-50 transition active:scale-95 cursor-pointer" 
                        id="share-btn" 
                        onclick="shareStore('{{ $shop->name }}', '{{ url()->current() }}')" 
                        type="button" 
                        title="Compartir catálogo"
                    >
                        <svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </button>

                    <!-- Authenticated Seller controls -->
                    @auth
                        @can('update', $shop)
                            <a class="rounded-xl bg-slate-900 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition shadow-2xs inline-flex items-center gap-1 shrink-0" href="{{ route('seller.shops.products.index', $shop) }}">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="hidden md:inline">Administrar</span>
                            </a>
                        @endcan
                    @endauth
                </div>
            </div>
        </header>

        <!-- Store Header Banner (Clean, Minimalist Puntto-Style) -->
        <section class="border-b border-slate-200/80 bg-white">
            <div class="mx-auto max-w-[1400px] px-4 pt-4 sm:px-8 sm:pt-6">
                <div class="relative h-32 overflow-hidden rounded-2xl bg-slate-900 shadow-sm sm:h-44">
                    @if ($shop->cover_url)
                        <img src="{{ $shop->cover_url }}" alt="Portada de {{ $shop->name }}" class="absolute inset-0 h-full w-full object-cover" loading="eager" fetchpriority="high">
                    @else
                        <div class="absolute inset-0 store-secondary-bg"></div>
                    @endif
                    <div class="store-cover-overlay absolute inset-0"></div>
                    <div class="relative flex h-full items-end justify-between gap-3 p-4 sm:p-6">
                        <div class="max-w-xl text-white">
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-white/70">Vitrina digital</p>
                            <p class="mt-1 text-lg font-black tracking-tight sm:text-2xl">Descubre lo mejor de {{ $shop->name }}</p>
                        </div>
                        <span class="hidden rounded-full bg-white/15 px-3 py-1.5 text-[11px] font-bold text-white backdrop-blur sm:inline-flex">Compra directo por WhatsApp</span>
                    </div>
                </div>
            </div>
            <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-8 sm:py-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <!-- Profile Info -->
                    <div class="flex items-start sm:items-center gap-4">
                        @if ($shop->logo_url)
                            <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-16 w-16 sm:h-20 sm:w-20 shrink-0 rounded-2xl object-cover shadow-xs border border-slate-200">
                        @else
                            <div class="flex h-16 w-16 sm:h-20 sm:w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-2xl sm:text-3xl font-black text-white shadow-xs">
                                {{ str($shop->name)->substr(0, 1)->upper() }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 truncate">{{ $shop->name }}</h1>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 shrink-0 border border-emerald-200/60">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Catálogo Verificado
                                </span>
                            </div>
                            @if ($shop->description)
                                <p class="mt-1.5 max-w-2xl text-xs sm:text-sm leading-relaxed text-slate-600">{{ $shop->description }}</p>
                            @endif
                            <div class="mt-2.5 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                @if ($shop->offers_shipping)
                                    <span class="inline-flex items-center gap-1 text-slate-700 font-medium">
                                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Envíos a todo el país
                                    </span>
                                @endif
                                @if ($shop->address)
                                    @if ($shop->maps_url)
                                        <a class="inline-flex items-center gap-1 font-medium text-slate-600 hover:text-blue-700 transition" href="{{ $shop->maps_url }}" rel="noopener noreferrer" target="_blank">⌖ {{ $shop->address }}</a>
                                    @else
                                        <span class="inline-flex items-center gap-1 font-medium text-slate-600">⌖ {{ $shop->address }}</span>
                                    @endif
                                @endif
                                @if ($shop->instagram)
                                    <a class="inline-flex items-center gap-1 font-medium text-slate-600 hover:text-pink-600 transition" href="https://instagram.com/{{ ltrim($shop->instagram, '@') }}" rel="noopener noreferrer" target="_blank">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                        {{ $shop->instagram }}
                                    </a>
                                @endif
                                @if ($businessHoursStatus)
                                    <span class="inline-flex items-center gap-1 font-medium {{ $businessHoursStatus['open'] ? 'text-emerald-700' : 'text-slate-600' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $businessHoursStatus['open'] ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                        {{ $businessHoursStatus['label'] }}
                                    </span>
                                @endif
                                <x-report-modal type="shop" :id="$shop->public_id" :name="$shop->name" />
                            </div>
                            @if ($businessHours)
                                <details class="mt-4 max-w-md rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                    <summary class="cursor-pointer font-bold">Ver horario semanal</summary>
                                    <div class="mt-3 space-y-1.5">
                                        @foreach ($businessHours as $day => $dayHours)
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-semibold">{{ \App\Services\ShopHoursService::DAYS[$day] }}</span>
                                                <span class="text-slate-500">{{ $dayHours['closed'] ? 'Cerrado' : ($dayHours['all_day'] ? '24 horas' : $dayHours['open'].' – '.$dayHours['close']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    </div>

                    <!-- Search Bar & Filters Quick Access -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                        <!-- Scoped Store Search Form -->
                        <form method="GET" action="{{ route('shops.show', $shop) }}" class="relative w-full sm:w-72 lg:w-80">
                            @foreach (request()->except(['q', 'page']) as $k => $v)
                                @if (is_string($v) && $v !== '')
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <div class="relative flex items-center">
                                <input 
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2 pl-9 pr-20 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 shadow-2xs transition" 
                                    type="search" 
                                    name="q" 
                                    value="{{ $searchQuery }}" 
                                    placeholder="Buscar en {{ $shop->name }}...">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <button class="absolute right-1 rounded-lg bg-blue-600 px-3 py-1 text-xs font-bold text-white hover:bg-blue-700 transition cursor-pointer shadow-2xs" type="submit">
                                    Buscar
                                </button>
                            </div>
                        </form>

                        <!-- Filter Drawer Trigger Button (Puntto-Style Mobile & Desktop compact) -->
                        <button 
                            type="button" 
                            @click="openFilterDrawer = true" 
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition active:scale-95 cursor-pointer shrink-0"
                        >
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <span>Filtros</span>
                            @if ($activeFiltersCount > 0)
                                <span class="flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-black text-white">
                                    {{ $activeFiltersCount }}
                                </span>
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Area -->
        <main class="mx-auto max-w-[1400px] px-4 py-5 sm:px-8 pb-28 sm:pb-12">
            <!-- Legacy Shared Cart Form (when items present in query) -->
            @if ($cartItems->isNotEmpty())
                <section class="mb-6 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm ring-4 ring-blue-50/50">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-blue-600">Carrito compartido por WhatsApp</p>
                            <h2 class="mt-1 text-base font-extrabold text-slate-900">Productos seleccionados</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Confirma las cantidades y procesa la venta.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700"><span x-text="cartItems.length"></span> producto(s)</span>
                    </div>
                    <form id="shared-cart-form" @submit.prevent="checkoutCart" class="mt-4 space-y-3">
                        @csrf
                        @foreach ($cartItems as $index => $cartProduct)
                            @php
                                $cartInventory = $cartProduct->inventory;
                                $cartMaxStock = $cartInventory?->track_inventory ? max(1, (int) $cartInventory->stock_quantity) : 10000;
                            @endphp
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                                    @if ($cartProduct->images->isNotEmpty())
                                        <img src="{{ $cartProduct->images->first()->url }}" alt="{{ $cartProduct->name }}" class="h-full w-full object-contain p-1" loading="lazy" decoding="async">
                                    @else
                                        <svg class="h-5 w-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('products.show', [$shop, $cartProduct]) }}" class="text-xs font-bold text-slate-900 hover:text-blue-700">{{ $cartProduct->name }}</a>
                                    <p class="text-[11px] text-slate-500 font-medium">
                                        {{ $cartProduct->isDecant() ? $cartProduct->volume_ml.' ml · ' : '' }}RD$ {{ number_format((float) $cartProduct->price, 0) }} c/u
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label for="shared-cart-quantity-{{ $index }}" class="text-[11px] font-semibold text-slate-500">Cantidad</label>
                                    <input id="shared-cart-quantity-{{ $index }}" type="number" min="1" max="{{ $cartMaxStock }}" x-model.number="cartItems[{{ $index }}].quantity" class="w-16 rounded-lg border border-slate-300 bg-white px-2 py-1 text-center text-xs font-bold" required>
                                    <span class="w-24 text-right text-xs font-extrabold text-slate-900 tabular-nums whitespace-nowrap" x-text="'RD$ ' + Number(cartItems[{{ $index }}].quantity * cartItems[{{ $index }}].price).toLocaleString('es-DO', { maximumFractionDigits: 0 })"></span>
                                </div>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total</span>
                                <p class="text-xl font-black text-slate-900 tabular-nums whitespace-nowrap" x-text="'RD$ ' + Number(sharedCartTotal).toLocaleString('es-DO', { maximumFractionDigits: 0 })"></p>
                            </div>
                            @auth
                                @can('update', $shop)
                                    <button type="submit" :disabled="checkoutBusy" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-60 transition cursor-pointer">
                                        <span x-show="!checkoutBusy">Cobrar y descontar inventario</span>
                                        <span x-show="checkoutBusy">Procesando cobro...</span>
                                    </button>
                                @endcan
                            @endauth
                        </div>
                        <p x-show="checkoutError" x-text="checkoutError" class="text-xs font-bold text-rose-700"></p>
                    </form>
                </section>
            @endif

            <!-- Categories Horizontal Bar (Puntto Touch Scrollable Tabs) -->
            @if ($categories->isNotEmpty())
                <div class="mb-4">
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-medium no-scrollbar">
                        <a 
                            class="whitespace-nowrap rounded-full px-3.5 py-1.5 transition {{ !$selectedCategory ? 'bg-slate-900 text-white font-bold shadow-2xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}" 
                            href="{{ request()->fullUrlWithQuery(['categoria' => null, 'page' => null]) }}"
                        >
                            Todos ({{ $shop->products()->where('moderation_status', 'active')->count() }})
                        </a>
                        @foreach ($categories as $category)
                            <a 
                                class="whitespace-nowrap rounded-full px-3.5 py-1.5 transition {{ $selectedCategory?->id === $category->id ? 'bg-slate-900 text-white font-bold shadow-2xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}" 
                                href="{{ request()->fullUrlWithQuery(['categoria' => $category->slug, 'page' => null]) }}"
                            >
                                {{ $category->name }} ({{ $category->products_count }})
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Active Filter Chips Banner -->
            @if ($hasActiveFilters)
                <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl bg-blue-50/70 p-3 border border-blue-200 text-xs">
                    <span class="font-bold text-blue-950 text-[11px]">Filtros aplicados:</span>

                    @if ($searchQuery)
                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-slate-700 font-medium border border-blue-200 text-[11px] shadow-2xs">
                            <span>Mostrando resultados para <strong class="text-blue-950">"{{ $searchQuery }}"</strong></span>
                            <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}" class="font-bold text-rose-600 hover:text-rose-700">Limpiar búsqueda ✕</a>
                        </div>
                    @endif

                    @if ($selectedCategory)
                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-slate-700 font-medium border border-blue-200 text-[11px] shadow-2xs">
                            <span>Categoría: <strong class="text-blue-950">{{ $selectedCategory->name }}</strong></span>
                            <a href="{{ request()->fullUrlWithQuery(['categoria' => null, 'page' => null]) }}" class="font-bold text-rose-600 hover:text-rose-700">✕</a>
                        </div>
                    @endif

                    @if ($stock === 'available')
                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-slate-700 font-medium border border-blue-200 text-[11px] shadow-2xs">
                            <span>Solo en stock</span>
                            <a href="{{ request()->fullUrlWithQuery(['stock' => null, 'page' => null]) }}" class="font-bold text-rose-600 hover:text-rose-700">✕</a>
                        </div>
                    @endif

                    @if ($minPrice || $maxPrice)
                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-slate-700 font-medium border border-blue-200 text-[11px] shadow-2xs">
                            <span>Precio: <strong class="text-blue-950 tabular-nums">RD$ {{ $minPrice ? number_format((float)$minPrice) : '0' }} - {{ $maxPrice ? number_format((float)$maxPrice) : '∞' }}</strong></span>
                            <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null, 'page' => null]) }}" class="font-bold text-rose-600 hover:text-rose-700">✕</a>
                        </div>
                    @endif

                    @if ($sort && $sort !== 'latest')
                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-slate-700 font-medium border border-blue-200 text-[11px] shadow-2xs">
                            <span>Orden: <strong class="text-blue-950">{{ $sort === 'price_asc' ? 'Menor precio' : ($sort === 'price_desc' ? 'Mayor precio' : 'Nombre A-Z') }}</strong></span>
                            <a href="{{ request()->fullUrlWithQuery(['sort' => null, 'page' => null]) }}" class="font-bold text-rose-600 hover:text-rose-700">✕</a>
                        </div>
                    @endif

                    <a href="{{ route('shops.show', $shop) }}" class="ml-auto inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1 text-white font-bold hover:bg-rose-700 transition shadow-2xs text-[11px]">
                        Limpiar todos ✕
                    </a>
                </div>
            @endif

            <!-- Products Grid Title & Count -->
            <div class="mb-3.5 flex items-center justify-between">
                <div>
                    <h2 class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight">
                        {{ $selectedCategory ? $selectedCategory->name : ($searchQuery ? 'Resultados de búsqueda' : 'Catálogo de productos') }}
                    </h2>
                    <p class="text-[11px] text-slate-500">
                        {{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}
                    </p>
                </div>

                <!-- Desktop Sort quick select -->
                <div class="hidden sm:flex items-center gap-2 text-xs">
                    <span class="text-slate-400 font-medium">Ordenar:</span>
                    <form method="GET" action="{{ route('shops.show', $shop) }}" class="inline">
                        @foreach (request()->except(['sort', 'page']) as $k => $v)
                            @if (is_string($v) && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <select 
                            name="sort" 
                            onchange="this.form.submit()" 
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 focus:border-blue-600 focus:outline-none cursor-pointer shadow-2xs"
                        >
                            <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Más recientes</option>
                            <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Menor precio</option>
                            <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Mayor precio</option>
                            <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Nombre (A-Z)</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Product Grid (Puntto Minimal Clean Cards) -->
            <div class="grid grid-cols-2 gap-2.5 sm:gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5">
                @forelse ($products as $product)
                    @php
                        $isProductAvailable = $product->isInventoryTracked()
                            ? (!$product->inventory->isOutOfStock() && $product->availability_status->value !== 'out_of_stock')
                            : ($product->availability_status->value === 'available');
                        $productMaxStock = $product->isInventoryTracked() ? max(1, (int) $product->inventory->stock_quantity) : 9999;
                        $productImgUrl = $product->images->isNotEmpty() ? $product->images->first()->url : null;
                        $productJsData = [
                            'id' => $product->public_id,
                            'name' => $product->name,
                                    'price' => $product->currentPrice(),
                            'image' => $productImgUrl,
                            'url' => route('products.show', [$shop, $product]),
                            'maxStock' => $productMaxStock,
                            'isAvailable' => $isProductAvailable,
                        ];
                    @endphp
                    <article class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs transition-all duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-md">
                        <!-- Product Image Link -->
                        <a class="relative aspect-square overflow-hidden bg-slate-50 block" href="{{ route('products.show', [$shop, $product]) }}">
                            @if ($productImgUrl)
                                <img src="{{ $productImgUrl }}" alt="{{ $product->name }}" class="h-full w-full object-cover object-center transition duration-300 group-hover:scale-105" loading="lazy" decoding="async">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-slate-300">
                                    <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Zm5-12h.01" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                </div>
                            @endif

                            <!-- Stock Badge on Image -->
                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                @if (!$isProductAvailable)
                                    <span class="rounded-md bg-rose-600/95 px-2 py-0.5 text-[9px] font-black text-white shadow-2xs backdrop-blur-xs">
                                        Agotado
                                    </span>
                                @elseif ($product->isInventoryTracked() && $product->inventory->isLowStock($product))
                                    <span class="rounded-md bg-amber-500/95 px-2 py-0.5 text-[9px] font-black text-white shadow-2xs backdrop-blur-xs">
                                        ¡Últimas {{ $product->inventory->stock_quantity }} unid.!
                                    </span>
                                @endif
                            </div>

                            @if ($product->images->count() > 1)
                                <span class="absolute bottom-2 right-2 rounded-md bg-black/60 px-1.5 py-0.5 text-[9px] font-bold text-white backdrop-blur-xs">
                                    1/{{ $product->images->count() }}
                                </span>
                            @endif
                        </a>

                        <!-- Product Info & Actions -->
                        <div class="flex flex-1 flex-col p-3">
                            <a href="{{ route('products.show', [$shop, $product]) }}" class="block">
                                <h3 class="line-clamp-2 min-h-8 text-xs font-bold text-slate-900 group-hover:text-blue-600 transition leading-snug">
                                    {{ $product->name }}
                                </h3>
                            </a>

                            <!-- Price (Rule 8 strictly compliant) -->
                            <div class="mt-2 flex items-end justify-between gap-2">
                                <div>
                                    @if ($product->brand)
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $product->brand }}</p>
                                    @endif
                                    <p class="text-sm sm:text-base font-extrabold tracking-tight text-slate-900 tabular-nums whitespace-nowrap">
                                        RD$ {{ number_format($product->currentPrice(), 0) }}
                                    </p>
                                    @if ($product->isOnSale())
                                        <p class="text-[11px] text-slate-400 line-through">RD$ {{ number_format((float) $product->price, 0) }}</p>
                                    @endif
                                </div>
                                @if ($product->isOnSale())
                                    <span class="rounded-md bg-rose-50 px-1.5 py-1 text-[10px] font-black text-rose-600">-{{ $product->discountPercent() }}%</span>
                                @endif
                            </div>

                            <!-- Cart Add / Quantity Selector Button (Puntto Pattern) -->
                            <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between gap-1.5">
                                @if ($isProductAvailable)
                                    <!-- When NOT in cart yet: Clean "+ Agregar" button -->
                                    <template x-if="getItemQuantity(@js($product->public_id)) === 0">
                                        <button 
                                            type="button" 
                                            @click.stop.prevent="addToCart(@js($productJsData))"
                                            class="w-full inline-flex items-center justify-center gap-1 rounded-xl bg-slate-900 hover:bg-blue-600 text-white py-1.5 px-2 text-[11px] font-bold shadow-2xs transition active:scale-95 cursor-pointer"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                            <span>Agregar</span>
                                        </button>
                                    </template>

                                    <!-- When ALREADY in cart: Stepper [- 1 +] -->
                                    <template x-if="getItemQuantity(@js($product->public_id)) > 0">
                                        <div class="w-full flex items-center justify-between rounded-xl bg-blue-50 border border-blue-200 p-0.5">
                                            <button 
                                                type="button" 
                                                @click.stop.prevent="updateCartQuantity(@js($product->public_id), -1)"
                                                class="h-6 w-6 flex items-center justify-center rounded-lg bg-white text-slate-800 font-black text-xs hover:bg-slate-100 shadow-2xs active:scale-95 cursor-pointer"
                                            >
                                                -
                                            </button>
                                            <span 
                                                class="text-xs font-black text-blue-900 tabular-nums px-1" 
                                                x-text="getItemQuantity(@js($product->public_id))"
                                            ></span>
                                            <button 
                                                type="button" 
                                                @click.stop.prevent="updateCartQuantity(@js($product->public_id), 1)"
                                                class="h-6 w-6 flex items-center justify-center rounded-lg bg-blue-600 text-white font-black text-xs hover:bg-blue-700 shadow-2xs active:scale-95 cursor-pointer"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </template>
                                @else
                                    <span class="w-full text-center py-1.5 text-[10px] font-bold text-slate-400 bg-slate-100 rounded-xl">
                                        No disponible
                                    </span>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                        <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                        <p class="mt-2 text-sm font-bold text-slate-800">
                            @if ($searchQuery)
                                No encontramos productos para "{{ $searchQuery }}" en {{ $shop->name }}
                            @else
                                No hay productos en esta sección
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Prueba restableciendo los filtros o buscando con otros términos.
                        </p>
                        @if ($selectedCategory || $searchQuery || $hasActiveFilters)
                            <a class="mt-3 inline-block rounded-xl bg-blue-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition" href="{{ route('shops.show', $shop) }}">
                                Ver todo el catálogo de {{ $shop->name }}
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if ($products->hasPages())
                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif

            <x-ad-slot position="catalog_between_rows" :shop="$shop" />

            <!-- Sticky Bottom Store Floating Contact Bar for Mobile Screens -->
            <div class="fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur-md border-t border-slate-200 px-4 py-2.5 shadow-2xl flex items-center justify-between gap-3 sm:hidden">
                <div class="flex items-center gap-2 min-w-0">
                    @if ($shop->logo_url)
                        <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-8 w-8 shrink-0 rounded-lg object-cover border border-slate-200">
                    @else
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-xs font-black text-white">
                            {{ str($shop->name)->substr(0, 1)->upper() }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-900 truncate">{{ $shop->name }}</p>
                        <span class="text-[10px] font-semibold text-emerald-700 flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Catálogo Verificado
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button 
                        type="button" 
                        @click="openCartDrawer = true" 
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 shadow-2xs active:scale-95"
                    >
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span x-text="cartCount > 0 ? cartCount : 'Pedido'"></span>
                    </button>
                    <a 
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-2xs active:scale-95" 
                        href="{{ route('track.wa.shop', $shop) }}" 
                        data-wa-target="https://wa.me/{{ $shop->whatsapp_country_code.$shop->whatsapp_number }}" 
                        rel="noopener noreferrer" 
                        target="_blank"
                    >
                        <svg class="h-3.5 w-3.5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                </div>
            </div>

            <!-- Sticky Cart Floating Pill Banner (Whenever items in cart) -->
            <div 
                x-show="cartCount > 0" 
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                class="fixed bottom-16 sm:bottom-6 inset-x-4 z-40 mx-auto max-w-md"
            >
                <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-900/95 backdrop-blur-md p-3 text-white shadow-2xl border border-slate-700/60 ring-1 ring-white/10">
                    <div class="flex items-center gap-2.5 min-w-0 pl-1">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white font-bold text-xs shadow-xs">
                            🛍️
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold truncate">
                                <span x-text="cartCount"></span> artículo(s) en tu pedido
                            </p>
                            <p class="text-xs font-extrabold text-emerald-400 tabular-nums whitespace-nowrap" x-text="'RD$ ' + cartTotal.toLocaleString('es-DO', { maximumFractionDigits: 0 })"></p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        @click="openCartDrawer = true" 
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-500 transition shadow-xs active:scale-95 cursor-pointer whitespace-nowrap"
                    >
                        <span>Ver pedido</span>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </main>

        <!-- Slide-over Drawer 1: WhatsApp Multi-Product Cart Drawer -->
        <div 
            x-show="openCartDrawer" 
            x-cloak 
            class="relative z-50" 
            aria-labelledby="slide-over-cart-title" 
            role="dialog" 
            aria-modal="true"
        >
            <!-- Backdrop -->
            <div 
                x-show="openCartDrawer"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
                @click="openCartDrawer = false"
            ></div>

            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
                        <div 
                            x-show="openCartDrawer"
                            x-transition:enter="transform transition ease-in-out duration-300"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in-out duration-300"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"
                            class="pointer-events-auto w-screen max-w-full sm:max-w-md bg-white shadow-2xl flex flex-col h-full"
                        >
                            <!-- Drawer Header -->
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 font-bold">
                                        🛍️
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-extrabold text-slate-900" id="slide-over-cart-title">Tu Pedido</h2>
                                        <p class="text-[11px] text-slate-500 font-medium truncate">{{ $shop->name }}</p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="openCartDrawer = false" 
                                    class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Drawer Body (Items list & customer details) -->
                            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                                <!-- Empty state -->
                                <template x-if="cart.length === 0">
                                    <div class="py-12 text-center">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                                            🛒
                                        </div>
                                        <p class="mt-3 text-sm font-bold text-slate-900">Tu pedido está vacío</p>
                                        <p class="mt-1 text-xs text-slate-500 max-w-xs mx-auto">
                                            Agrega productos del catálogo para enviárselos al vendedor por WhatsApp.
                                        </p>
                                        <button 
                                            type="button" 
                                            @click="openCartDrawer = false" 
                                            class="mt-4 inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-blue-600 transition cursor-pointer"
                                        >
                                            Explorar productos
                                        </button>
                                    </div>
                                </template>

                                <!-- Items List -->
                                <template x-if="cart.length > 0">
                                    <div class="space-y-2.5">
                                        <template x-for="item in cart" :key="item.id">
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-3 space-y-2.5">
                                                <!-- Fila superior: imagen, nombre, precio unitario y eliminar -->
                                                <div class="flex items-start gap-3">
                                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                        <template x-if="item.image">
                                                            <img :src="item.image" :alt="item.name" class="h-full w-full object-cover">
                                                        </template>
                                                        <template x-if="!item.image">
                                                            <div class="flex h-full w-full items-center justify-center text-slate-300">
                                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-xs font-bold text-slate-900 line-clamp-2 leading-snug" x-text="item.name"></p>
                                                        <p class="text-[11px] font-semibold text-slate-500 tabular-nums whitespace-nowrap mt-0.5" x-text="'RD$ ' + item.price.toLocaleString('es-DO', { maximumFractionDigits: 0 }) + ' c/u'"></p>
                                                    </div>

                                                    <button 
                                                        type="button" 
                                                        @click="removeFromCart(item.id)" 
                                                        class="text-slate-400 hover:text-rose-600 p-1 shrink-0 -mr-1 -mt-1 cursor-pointer"
                                                        title="Eliminar artículo"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>

                                                <!-- Fila inferior: stepper y subtotal -->
                                                <div class="flex items-center justify-between border-t border-slate-200/60 pt-2">
                                                    <div class="flex items-center rounded-lg border border-slate-200 bg-white p-0.5 shadow-2xs">
                                                        <button 
                                                            type="button" 
                                                            @click="updateCartQuantity(item.id, -1)" 
                                                            class="h-6 w-6 flex items-center justify-center rounded text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                                                        >-</button>
                                                        <span class="w-7 text-center text-xs font-black text-slate-900 tabular-nums" x-text="item.quantity"></span>
                                                        <button 
                                                            type="button" 
                                                            @click="updateCartQuantity(item.id, 1)" 
                                                            class="h-6 w-6 flex items-center justify-center rounded text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                                                        >+</button>
                                                    </div>
                                                    <span class="text-right text-xs sm:text-sm font-extrabold text-slate-900 tabular-nums whitespace-nowrap" x-text="'RD$ ' + (item.quantity * item.price).toLocaleString('es-DO', { maximumFractionDigits: 0 })"></span>
                                                </div>
                                            </div>
                                        </template>

                                        <div class="pt-2 flex justify-end">
                                            <button 
                                                type="button" 
                                                @click="clearCart()" 
                                                class="text-[11px] font-semibold text-rose-600 hover:underline cursor-pointer"
                                            >
                                                Vaciar pedido
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Customer details for WhatsApp prefill -->
                                <template x-if="cart.length > 0">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
                                        <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Datos para la entrega</p>
                                        
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-700 mb-1" for="cart-customer-name">Tu nombre (opcional)</label>
                                            <input 
                                                id="cart-customer-name" 
                                                type="text" 
                                                x-model="customerName" 
                                                placeholder="Ej. Juan Pérez" 
                                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                                            >
                                        </div>

                                        @if ($shop->offers_shipping)
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Método de entrega</label>
                                                <div class="grid grid-cols-2 gap-2 text-xs">
                                                    <button 
                                                        type="button" 
                                                        @click="deliveryType = 'delivery'" 
                                                        :class="deliveryType === 'delivery' ? 'bg-blue-600 text-white font-bold border-blue-600' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" 
                                                        class="rounded-xl border py-2 px-2.5 text-center transition cursor-pointer"
                                                    >
                                                        🛵 A domicilio
                                                    </button>
                                                    <button 
                                                        type="button" 
                                                        @click="deliveryType = 'pickup'" 
                                                        :class="deliveryType === 'pickup' ? 'bg-blue-600 text-white font-bold border-blue-600' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" 
                                                        class="rounded-xl border py-2 px-2.5 text-center transition cursor-pointer"
                                                    >
                                                        🏪 Retiro en tienda
                                                    </button>
                                                </div>
                                            </div>
                                        @endif

                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-700 mb-1" for="cart-customer-notes">Dirección o notas (opcional)</label>
                                            <textarea 
                                                id="cart-customer-notes" 
                                                x-model="customerNotes" 
                                                rows="2" 
                                                placeholder="Ej. Calle Principal #12, Santo Domingo / Talla 42" 
                                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                                            ></textarea>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Drawer Footer: Subtotal & Send to WhatsApp -->
                            <template x-if="cart.length > 0">
                                <div class="border-t border-slate-200 bg-slate-50/70 p-5 space-y-3">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total a pagar</span>
                                        <span class="text-2xl font-black text-slate-900 tabular-nums whitespace-nowrap" x-text="'RD$ ' + cartTotal.toLocaleString('es-DO', { maximumFractionDigits: 0 })"></span>
                                    </div>

                                    <button 
                                        type="button" 
                                        @click="sendWhatsAppOrder()" 
                                        :disabled="sendingOrder"
                                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3.5 px-4 text-sm font-extrabold text-white shadow-md hover:bg-emerald-700 transition active:scale-98 cursor-pointer"
                                    >
                                        <svg class="h-5 w-5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                        <span x-text="sendingOrder ? 'Preparando pedido...' : 'Enviar pedido por WhatsApp'"></span>
                                    </button>

                                    <p x-show="orderError" x-text="orderError" class="rounded-lg bg-rose-50 px-3 py-2 text-[11px] font-semibold text-rose-700" role="alert"></p>

                                    <p class="text-center text-[11px] text-slate-500 font-medium">
                                        Trato directo con el vendedor. Sin comisiones.
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide-over Drawer 2: Filter Drawer (Puntto Pattern) -->
        <div 
            x-show="openFilterDrawer" 
            x-cloak 
            class="relative z-50" 
            aria-labelledby="slide-over-filter-title" 
            role="dialog" 
            aria-modal="true"
        >
            <!-- Backdrop -->
            <div 
                x-show="openFilterDrawer"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
                @click="openFilterDrawer = false"
            ></div>

            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
                        <div 
                            x-show="openFilterDrawer"
                            x-transition:enter="transform transition ease-in-out duration-300"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in-out duration-300"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"
                            class="pointer-events-auto w-screen max-w-full sm:max-w-sm bg-white shadow-2xl flex flex-col h-full"
                        >
                            <!-- Drawer Header -->
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                    <h2 class="text-sm font-extrabold text-slate-900" id="slide-over-filter-title">Filtros del Catálogo</h2>
                                </div>
                                <button 
                                    type="button" 
                                    @click="openFilterDrawer = false" 
                                    class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Filter Options Form -->
                            <form method="GET" action="{{ route('shops.show', $shop) }}" class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                                @if ($searchQuery)
                                    <input type="hidden" name="q" value="{{ $searchQuery }}">
                                @endif
                                @if ($selectedCategory)
                                    <input type="hidden" name="categoria" value="{{ $selectedCategory->slug }}">
                                @endif
                                @if ($stock === 'available')
                                    <input type="hidden" name="stock" value="available">
                                @endif

                                <!-- Stock Status Filter -->
                                <div>
                                    <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2">Disponibilidad</label>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <a 
                                            href="{{ request()->fullUrlWithQuery(['stock' => null, 'page' => null]) }}" 
                                            class="rounded-xl border py-2 px-2 text-center transition {{ $stock !== 'available' ? 'bg-slate-900 text-white font-bold border-slate-900' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' }}"
                                        >
                                            Todos
                                        </a>
                                        <a 
                                            href="{{ request()->fullUrlWithQuery(['stock' => 'available', 'page' => null]) }}" 
                                            class="rounded-xl border py-2 px-2 text-center transition {{ $stock === 'available' ? 'bg-emerald-600 text-white font-bold border-emerald-600' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' }}"
                                        >
                                            ● Solo en stock
                                        </a>
                                    </div>
                                </div>

                                <!-- Sorting Filter -->
                                <div>
                                    <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2" for="drawer-sort-select">Ordenar por</label>
                                    <select 
                                        id="drawer-sort-select"
                                        name="sort" 
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-blue-600 focus:outline-none"
                                    >
                                        <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Más recientes</option>
                                        <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Menor precio</option>
                                        <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Mayor precio</option>
                                        <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Nombre (A-Z)</option>
                                    </select>
                                </div>

                                <!-- Categories List -->
                                @if ($categories->isNotEmpty())
                                    <div>
                                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2">Categorías</label>
                                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                            <a 
                                                href="{{ request()->fullUrlWithQuery(['categoria' => null, 'page' => null]) }}" 
                                                class="flex items-center justify-between rounded-xl px-3 py-2 text-xs transition {{ !$selectedCategory ? 'bg-blue-50 text-blue-700 font-bold border border-blue-200' : 'text-slate-700 hover:bg-slate-50' }}"
                                            >
                                                <span>Todas las categorías</span>
                                                <span class="text-[11px] text-slate-400">({{ $shop->products()->where('moderation_status', 'active')->count() }})</span>
                                            </a>
                                            @foreach ($categories as $cat)
                                                <a 
                                                    href="{{ request()->fullUrlWithQuery(['categoria' => $cat->slug, 'page' => null]) }}" 
                                                    class="flex items-center justify-between rounded-xl px-3 py-2 text-xs transition {{ $selectedCategory?->id === $cat->id ? 'bg-blue-50 text-blue-700 font-bold border border-blue-200' : 'text-slate-700 hover:bg-slate-50' }}"
                                                >
                                                    <span class="truncate">{{ $cat->name }}</span>
                                                    <span class="text-[11px] text-slate-400">({{ $cat->products_count }})</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Dynamic Attribute Filters -->
                                @foreach ($attributeDefinitions as $definition)
                                    @php $selectedValues = $selectedAttributes[$definition->slug] ?? []; @endphp
                                    <div>
                                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2">{{ $definition->name }}</label>
                                        <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                            @foreach ($definition->values->pluck('value')->unique()->sort()->values() as $value)
                                                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                                                    <input type="checkbox" name="atributo[{{ $definition->slug }}][]" value="{{ $value }}" @checked(in_array($value, $selectedValues, true)) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="truncate">{{ $value }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                <!-- Price Range Filter -->
                                <div>
                                    <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2">Rango de Precio (RD$)</label>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="number" 
                                            name="min_price" 
                                            value="{{ $minPrice }}" 
                                            placeholder="Mínimo" 
                                            min="0" 
                                            class="w-1/2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                                        >
                                        <span class="text-slate-400">-</span>
                                        <input 
                                            type="number" 
                                            name="max_price" 
                                            value="{{ $maxPrice }}" 
                                            placeholder="Máximo" 
                                            min="0" 
                                            class="w-1/2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                                        >
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <a 
                                            href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => 1000, 'page' => null]) }}" 
                                            class="rounded-lg border px-2 py-1 text-[11px] font-semibold transition {{ $maxPrice == 1000 && empty($minPrice) ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                                        >
                                            &lt; 1K
                                        </a>
                                        <a 
                                            href="{{ request()->fullUrlWithQuery(['min_price' => 1000, 'max_price' => 5000, 'page' => null]) }}" 
                                            class="rounded-lg border px-2 py-1 text-[11px] font-semibold transition {{ $minPrice == 1000 && $maxPrice == 5000 ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                                        >
                                            1K - 5K
                                        </a>
                                        <a 
                                            href="{{ request()->fullUrlWithQuery(['min_price' => 5000, 'max_price' => null, 'page' => null]) }}" 
                                            class="rounded-lg border px-2 py-1 text-[11px] font-semibold transition {{ $minPrice == 5000 && empty($maxPrice) ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                                        >
                                            &gt; 5K
                                        </a>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-slate-200 space-y-2">
                                    <button 
                                        type="submit" 
                                        class="w-full rounded-xl bg-blue-600 py-3 text-xs font-extrabold text-white hover:bg-blue-700 transition shadow-2xs cursor-pointer"
                                    >
                                        Aplicar filtros
                                    </button>
                                    <a 
                                        href="{{ route('shops.show', $shop) }}" 
                                        class="block text-center rounded-xl border border-slate-200 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                                    >
                                        Limpiar todos
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-shop-footer :shop="$shop" />
    </div>

    @php
        $shopSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $shop->name,
            'description' => $shop->description ?: 'Vitrina digital de '.$shop->name,
            'url' => url()->current(),
            'image' => array_values(array_filter([$shop->cover_url, $shop->logo_url])),
            'telephone' => '+'.$shop->whatsapp_country_code.$shop->whatsapp_number,
            'address' => $shop->address ? [
                '@type' => 'PostalAddress',
                'addressLocality' => $shop->address,
                'addressCountry' => 'DO',
            ] : null,
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($shopSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <!-- Share Script -->
    <script>
        function shareStore(name, url) {
            if (navigator.share) {
                navigator.share({
                    title: name + ' en MiCatalogo',
                    text: 'Mira el catálogo de productos de ' + name,
                    url: url,
                }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Enlace del catálogo copiado al portapapeles');
                });
            }
        }
    </script>
</x-layouts.app>
