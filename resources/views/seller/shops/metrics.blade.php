<x-layouts.app :title="'Métricas & QR | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Métricas & QR']
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <!-- Shop Context & Local Navigation Tabs -->
            <x-seller.shop-header :shop="$shop" activeTab="metrics" />

            <!-- Performance KPI Cards -->
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Card 1: Visitas a la Vitrina -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Visitas (30 días)</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ number_format($metrics['views_30d']) }}</span>
                        <span class="text-xs font-medium text-slate-500">visitas</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">{{ number_format($metrics['views_7d']) }}</span> en los últimos 7 días · <span class="font-semibold text-slate-700">{{ number_format($metrics['total_views']) }}</span> total
                    </p>
                </div>

                <!-- Card 2: Contactos de WhatsApp -->
                <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Contactos WhatsApp</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-emerald-700 tracking-tight">{{ number_format($metrics['clicks_30d']) }}</span>
                        <span class="text-xs font-medium text-emerald-600">consultas</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">{{ number_format($metrics['clicks_7d']) }}</span> en los últimos 7 días · <span class="font-semibold text-slate-700">{{ number_format($metrics['total_clicks']) }}</span> total
                    </p>
                </div>

                <!-- Card 3: Tasa de Conversión -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tasa de Contacto</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $metrics['conversion_rate_30d'] }}%</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $metrics['conversion_rate_30d'] >= 5 ? 'bg-emerald-100 text-emerald-800' : ($metrics['conversion_rate_30d'] > 0 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600') }}">
                            {{ $metrics['conversion_rate_30d'] >= 5 ? 'Excelente' : ($metrics['conversion_rate_30d'] > 0 ? 'Activo' : 'Sin datos') }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Porcentaje de visitas que hacen clic para escribirte al WhatsApp
                    </p>
                </div>

                <!-- Card 4: Catálogo Activo -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Catálogo Activo</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $metrics['active_products_count'] }}</span>
                        <span class="text-xs font-medium text-slate-500">/ {{ $metrics['total_products_count'] }} publicados</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        <a href="{{ route('seller.shops.products.create', $shop) }}" class="font-semibold text-blue-600 hover:underline">
                            + Añadir nuevo producto
                        </a>
                    </p>
                </div>
            </div>

            <!-- Daily Trend Chart (30 Days) -->
            <div class="mb-8 rounded-xl border border-slate-200 bg-white p-6 shadow-2xs">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Actividad de los últimos 30 días</h3>
                        <p class="text-xs text-slate-500">Comportamiento diario de visitantes e intenciones de compra por WhatsApp</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-semibold">
                        <div class="flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-xs bg-blue-500 inline-block"></span>
                            <span class="text-slate-600">Visitas a vitrina</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-xs bg-emerald-500 inline-block"></span>
                            <span class="text-slate-600">Clics a WhatsApp</span>
                        </div>
                    </div>
                </div>

                <!-- Interactive Histogram -->
                <div class="h-48 w-full flex items-end gap-1 sm:gap-2 pt-6 border-b border-slate-200 pb-2">
                    @php
                        $maxVal = max($metrics['max_daily_views'], 5);
                    @endphp
                    @foreach ($metrics['daily_series'] as $day)
                        @php
                            $viewHeight = min(100, max(4, round(($day['views'] / $maxVal) * 100)));
                            $clickHeight = min(100, max(4, round(($day['clicks'] / $maxVal) * 100)));
                        @endphp
                        <div class="group relative flex-1 flex items-end justify-center gap-0.5 h-full">
                            <!-- Tooltip -->
                            <div class="pointer-events-none absolute -top-12 z-20 hidden rounded-md bg-slate-900 px-2.5 py-1.5 text-[10px] text-white shadow-md group-hover:block whitespace-nowrap">
                                <span class="font-bold text-slate-200">{{ $day['short_date'] }}</span>: 
                                <span class="text-blue-300 font-semibold">{{ $day['views'] }} vistas</span> · 
                                <span class="text-emerald-300 font-semibold">{{ $day['clicks'] }} clics</span>
                            </div>

                            <!-- Views Bar -->
                            <div 
                                style="height: {{ $day['views'] > 0 ? $viewHeight : 2 }}%;" 
                                class="w-full max-w-[12px] rounded-t-xs transition-all {{ $day['views'] > 0 ? 'bg-blue-400 group-hover:bg-blue-600' : 'bg-slate-100' }}"
                            ></div>

                            <!-- Clicks Bar -->
                            @if ($day['clicks'] > 0)
                                <div 
                                    style="height: {{ $clickHeight }}%;" 
                                    class="w-full max-w-[12px] rounded-t-xs bg-emerald-500 group-hover:bg-emerald-600 transition-all"
                                ></div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between items-center text-[10px] text-slate-400 font-mono mt-2 px-1">
                    <span>Hace 30 días</span>
                    <span>Hace 15 días</span>
                    <span>Hoy</span>
                </div>
            </div>

            <!-- Two-Column Layout: Top Products & Marketing / QR Tools -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Left: Top Products Rankings (2 Cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Top WhatsApp Inquiries (Best Selling / High Intent) -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-100 text-emerald-700 text-xs font-black">★</span>
                                    Productos más consultados en WhatsApp
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Tus productos estrella con mayor interés de compra directa</p>
                            </div>
                        </div>

                        @if ($metrics['top_by_clicks']->isEmpty())
                            <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                                Aún no se registran consultas de productos por WhatsApp. Comparte tu catálogo para comenzar a recibir mensajes.
                            </div>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($metrics['top_by_clicks'] as $product)
                                    <div class="flex items-center justify-between py-3 gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if ($product->image_url)
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg object-cover border border-slate-200 shrink-0">
                                            @else
                                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 font-bold text-xs shrink-0">
                                                    📦
                                                </div>
                                            @endif
                                            <div class="min-w-0 truncate">
                                                <a href="{{ route('products.show', [$shop, $product->slug]) }}" target="_blank" class="text-xs font-bold text-slate-900 hover:text-blue-600 truncate block">
                                                    {{ $product->name }}
                                                </a>
                                                <p class="text-[11px] text-slate-500 font-mono">
                                                    {{ $product->currency }} ${{ number_format($product->price, 2) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 text-right shrink-0">
                                            <div>
                                                <span class="text-xs font-extrabold text-emerald-700">{{ $product->clicks_count }}</span>
                                                <span class="text-[10px] text-slate-500 block">consultas</span>
                                            </div>
                                            <div class="hidden sm:block">
                                                <span class="text-xs font-semibold text-slate-700">{{ $product->views_count }}</span>
                                                <span class="text-[10px] text-slate-400 block">vistas</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Top Viewed Products -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-100 text-blue-700 text-xs font-black">👁</span>
                                    Productos más vistos
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Artículos con mayor visibilidad en tu vitrina</p>
                            </div>
                        </div>

                        @if ($metrics['top_by_views']->isEmpty())
                            <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                                Aún no hay visualizaciones registradas en los productos.
                            </div>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach ($metrics['top_by_views'] as $product)
                                    <div class="flex items-center justify-between py-3 gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if ($product->image_url)
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg object-cover border border-slate-200 shrink-0">
                                            @else
                                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 font-bold text-xs shrink-0">
                                                    📦
                                                </div>
                                            @endif
                                            <div class="min-w-0 truncate">
                                                <a href="{{ route('products.show', [$shop, $product->slug]) }}" target="_blank" class="text-xs font-bold text-slate-900 hover:text-blue-600 truncate block">
                                                    {{ $product->name }}
                                                </a>
                                                <p class="text-[11px] text-slate-500 font-mono">
                                                    {{ $product->currency }} ${{ number_format($product->price, 2) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 text-right shrink-0">
                                            <div>
                                                <span class="text-xs font-extrabold text-blue-700">{{ $product->views_count }}</span>
                                                <span class="text-[10px] text-slate-500 block">vistas</span>
                                            </div>
                                            <div class="hidden sm:block">
                                                <span class="text-xs font-semibold text-emerald-700">{{ $product->clicks_count }}</span>
                                                <span class="text-[10px] text-slate-400 block">consultas</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Right: Marketing & QR Code Toolkit -->
                <div class="space-y-6">
                    <!-- QR Card -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-2xs">
                        <div class="text-center">
                            <h3 class="text-base font-bold text-slate-900">Código QR de tu Tienda</h3>
                            <p class="mt-1 text-xs text-slate-500">Colócalo en tu mostrador, tarjetas o packaging para que tus clientes escaneen tu vitrina.</p>

                            <!-- QR Display Box -->
                            <div class="mx-auto my-5 flex h-48 w-48 items-center justify-center rounded-xl border border-slate-200 bg-white p-3 shadow-inner">
                                <div class="h-full w-full">
                                    {!! $qrSvg !!}
                                </div>
                            </div>

                            <!-- Direct Public Link Box with Copy Button -->
                            <div class="relative flex items-center rounded-lg border border-slate-200 bg-slate-50 p-2 text-xs">
                                <input 
                                    type="text" 
                                    readonly 
                                    id="shop-url-input"
                                    value="{{ $shopUrl }}" 
                                    class="w-full bg-transparent pr-16 text-slate-700 font-mono text-[11px] outline-none"
                                >
                                <button 
                                    type="button" 
                                    id="copy-link-btn"
                                    onclick="copyShopLink()" 
                                    class="absolute right-1 rounded-md bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 transition"
                                >
                                    Copiar
                                </button>
                            </div>
                            <span id="copy-feedback" class="mt-1 text-[11px] text-emerald-600 font-medium hidden">
                                ✓ ¡Enlace copiado al portapapeles!
                            </span>

                            <!-- Action Buttons -->
                            <div class="mt-4 flex flex-col gap-2">
                                <a 
                                    href="{{ route('seller.shops.qr.print', $shop) }}" 
                                    target="_blank" 
                                    class="flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    Imprimir cartel para mostrador
                                </a>

                                <a 
                                    href="{{ route('seller.shops.qr.download', $shop) }}" 
                                    class="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Descargar QR vectorial (.SVG)
                                </a>
                            </div>
                        </div>

                        <!-- Quick Share Row -->
                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block text-center mb-3">Compartir catálogo</span>
                            <div class="flex items-center justify-center gap-2">
                                <!-- WhatsApp -->
                                <a 
                                    href="https://wa.me/?text={{ urlencode('¡Hola! Te invito a ver nuestro catálogo digital en línea aquí: ' . $shopUrl) }}" 
                                    target="_blank" 
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition"
                                    title="Compartir en WhatsApp"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                </a>
                                <!-- Facebook -->
                                <a 
                                    href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shopUrl) }}" 
                                    target="_blank" 
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                                    title="Compartir en Facebook"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </a>
                                <!-- X / Twitter -->
                                <a 
                                    href="https://twitter.com/intent/tweet?text={{ urlencode('Conoce nuestro catálogo online en ' . $shop->name . ': ') }}&url={{ urlencode($shopUrl) }}" 
                                    target="_blank" 
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                                    title="Compartir en X"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tips for Conversion -->
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-5">
                        <h4 class="text-xs font-bold text-blue-900 flex items-center gap-1.5">
                            <span>💡</span> Consejos para aumentar ventas
                        </h4>
                        <ul class="mt-2.5 space-y-2 text-xs text-blue-800">
                            <li class="flex items-start gap-1.5">
                                <span class="text-blue-500 font-bold">•</span>
                                <span><strong>Precios visibles:</strong> Los productos con precio claro reciben 3 veces más contactos de clientes decididos a comprar.</span>
                            </li>
                            <li class="flex items-start gap-1.5">
                                <span class="text-blue-500 font-bold">•</span>
                                <span><strong>Fotos reales:</strong> Sube hasta 3 fotos nítidas por producto para generar confianza inmediata.</span>
                            </li>
                            <li class="flex items-start gap-1.5">
                                <span class="text-blue-500 font-bold">•</span>
                                <span><strong>QR en mostrador:</strong> Si tienes tienda física, imprime el cartel QR para que tus clientes puedan ver el catálogo completo en sus teléfonos.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-layouts.app>

<script>
function copyShopLink() {
    const input = document.getElementById('shop-url-input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const feedback = document.getElementById('copy-feedback');
        const btn = document.getElementById('copy-link-btn');
        feedback.classList.remove('hidden');
        btn.textContent = '¡Copiado!';
        btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        btn.classList.add('bg-emerald-600');
        setTimeout(() => {
            feedback.classList.add('hidden');
            btn.textContent = 'Copiar';
            btn.classList.remove('bg-emerald-600');
            btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        }, 3000);
    });
}
</script>
