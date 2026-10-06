<x-layouts.app :title="'Mi tienda | '.$shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Catálogo'],
        ['label' => 'Mi tienda'],
    ]" />

    <main class="min-h-screen bg-[#f7f7f6] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-[1600px] space-y-5">
            <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-end sm:justify-between sm:p-8">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">Catálogo / Mi tienda</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Mi tienda</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Todo lo de tu tienda en línea: compártela, decide cómo se ve y mira lo que le falta.</p>
                </div>
                <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-blue-700">Ver mi tienda <span aria-hidden="true">↗</span></a>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-5">
                    <nav class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm" aria-label="Secciones de Mi tienda">
                        @foreach (['resumen' => 'Resumen', 'apariencia' => 'Apariencia', 'contacto' => 'Contacto y horario', 'catalogo' => 'Catálogo', 'vitrinas' => 'Vitrinas', 'anuncios' => 'Anuncios', 'google' => 'Google'] as $anchor => $label)
                            <a href="#{{ $anchor }}" class="shrink-0 rounded-xl px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700 {{ $anchor === 'resumen' ? 'bg-blue-600 text-white hover:bg-blue-600 hover:text-white' : '' }}">{{ $label }}</a>
                        @endforeach
                    </nav>

                    <section id="resumen" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $shop->status === 'active' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span><p class="text-base font-black text-slate-950">{{ $shop->status === 'active' ? 'Tu tienda está abierta y recibe pedidos' : 'Tu tienda está suspendida' }}</p></div>
                                <p class="mt-2 break-all text-xs text-slate-500">{{ $shopUrl }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" x-data x-on:click="navigator.clipboard?.writeText(@js($shopUrl)); $el.textContent = 'Enlace copiado'" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-blue-200 hover:bg-blue-50">Copiar enlace</button>
                                <a href="https://wa.me/?text={{ urlencode('Mira mi catálogo: '.$shopUrl) }}" target="_blank" rel="noopener" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">Enviar por WhatsApp</a>
                                <a href="{{ route('seller.shops.qr.print', $shop) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-blue-200 hover:bg-blue-50">Imprimir QR</a>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-4">
                            @foreach ([['label' => 'Publicados', 'value' => $publishedCount, 'tone' => 'text-blue-700'], ['label' => 'Borrador', 'value' => max(0, $productCount - $publishedCount), 'tone' => 'text-amber-700'], ['label' => 'Sin foto', 'value' => $withoutPhotoCount, 'tone' => 'text-rose-700'], ['label' => 'Pedidos recibidos', 'value' => $orderCount, 'tone' => 'text-emerald-700']] as $stat)
                                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4"><p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $stat['label'] }}</p><p class="mt-2 text-2xl font-black {{ $stat['tone'] }}">{{ number_format($stat['value']) }}</p></div>
                            @endforeach
                        </div>
                    </section>

                    <section id="apariencia" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-black text-slate-950">Para que tu tienda se vea bien</h2><p class="mt-1 text-xs text-slate-500">Cada pendiente se arregla desde MiCatalogo, sin perder el contexto.</p></div><span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-black text-blue-700">{{ $completedChecklist }} / {{ count($checklist) }} completos</span></div>
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            @foreach ($checklist as $item)
                                <a href="{{ $item['url'] }}" class="group flex items-start gap-3 rounded-2xl border {{ $item['done'] ? 'border-emerald-100 bg-emerald-50/50' : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/40' }} p-4 transition">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $item['done'] ? 'bg-emerald-500 text-white' : 'bg-blue-100 text-blue-700' }} text-sm font-black">{{ $item['done'] ? '✓' : $loop->iteration }}</span>
                                    <span class="min-w-0"><span class="block text-sm font-black text-slate-800">{{ $item['label'] }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $item['description'] }}</span></span><span class="ml-auto text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-600">→</span>
                                </a>
                            @endforeach
                        </div>
                    </section>

                    <section id="contacto" class="grid gap-5 md:grid-cols-2">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Contacto y horario</p><h2 class="mt-2 text-lg font-black text-slate-950">Dile al cliente cómo encontrarte</h2><p class="mt-2 text-sm leading-6 text-slate-500">WhatsApp, dirección, mapa y redes se editan desde la configuración de la tienda.</p><a href="{{ route('seller.shops.edit', $shop) }}#address" class="mt-4 inline-flex rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Editar información</a></div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Vitrinas y anuncios</p><h2 class="mt-2 text-lg font-black text-slate-950">Haz que vuelva a visitarte</h2><p class="mt-2 text-sm leading-6 text-slate-500">Usa el enlace público y las métricas para saber qué productos generan interés.</p><a href="{{ route('seller.shops.metrics.index', $shop) }}" class="mt-4 inline-flex rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-black text-slate-700 hover:border-blue-200 hover:bg-blue-50">Ver métricas y QR</a></div>
                    </section>

                    <section id="catalogo" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-center justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Catálogo</p><h2 class="mt-2 text-lg font-black text-slate-950">Así empieza tu vitrina</h2></div><a href="{{ route('seller.shops.products.index', $shop) }}" class="text-xs font-black text-blue-700 hover:underline">Gestionar productos →</a></div><div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@forelse ($products as $product)<a href="{{ route('seller.shops.products.edit', [$shop, 'product' => $product]) }}" class="rounded-2xl border border-slate-200 p-3 transition hover:border-blue-200 hover:bg-blue-50/40">@if ($product->primaryImage?->url)<img src="{{ $product->primaryImage->url }}" alt="{{ $product->name }}" class="h-32 w-full rounded-xl bg-slate-100 object-contain">@else<div class="flex h-32 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-400">Sin foto</div>@endif<p class="mt-3 truncate text-sm font-black text-slate-800">{{ $product->name }}</p><p class="mt-1 text-xs text-slate-500">RD$ {{ number_format((float) $product->currentPrice(), 2) }}</p></a>@empty<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">Aún no tienes productos. <a class="font-bold text-blue-700 hover:underline" href="{{ route('seller.shops.products.create', $shop) }}">Crea el primero</a>.</div>@endforelse</div></section>

                    <section id="vitrinas" class="grid gap-5 md:grid-cols-2"><div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-base font-black text-slate-950">Vitrina pública</h2><p class="mt-2 text-sm leading-6 text-slate-500">Tu enlace funciona en el teléfono y no exige que el cliente instale nada.</p><a href="{{ $shopUrl }}" target="_blank" class="mt-4 inline-flex text-xs font-black text-blue-700 hover:underline">Abrir vitrina ↗</a></div><div id="anuncios" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-base font-black text-slate-950">Anuncios y difusión</h2><p class="mt-2 text-sm leading-6 text-slate-500">Comparte el enlace en WhatsApp, Instagram y tu mostrador con el QR.</p><a href="{{ route('seller.shops.metrics.index', $shop) }}" class="mt-4 inline-flex text-xs font-black text-blue-700 hover:underline">Ver difusión y métricas →</a></div></section>
                    <section id="google" class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-5"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Google</p><h2 class="mt-2 text-base font-black text-slate-950">Prepárala para que te encuentren</h2><p class="mt-2 text-sm leading-6 text-slate-600">Completa nombre, descripción, dirección y redes. Así tu enlace queda listo para compartir o indexar cuando actives esa opción.</p><a href="{{ route('seller.shops.edit', $shop) }}" class="mt-4 inline-flex text-xs font-black text-blue-700 hover:underline">Completar configuración →</a></section>
                </div>

                <aside class="space-y-5 xl:sticky xl:top-20 xl:self-start">
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Vista previa</p><h2 class="mt-1 text-lg font-black text-slate-950">Así la ve tu cliente</h2></div><span class="text-xs font-bold text-emerald-600">Actualizada</span></div><p class="mt-2 text-xs leading-5 text-slate-500">Tus cambios aparecen en la vitrina pública al guardar.</p><div class="mx-auto mt-5 max-w-[260px] overflow-hidden rounded-[2rem] border-8 border-slate-900 bg-white shadow-xl"><div class="h-5 bg-slate-900"></div><div class="bg-slate-50 p-3"><div class="flex items-center gap-2">@if ($shop->logo_url)<img src="{{ $shop->logo_url }}" alt="" class="h-7 w-7 rounded-lg object-cover">@else<span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-[10px] font-black text-white">{{ strtoupper(substr($shop->name, 0, 1)) }}</span>@endif<span class="truncate text-[11px] font-black text-slate-800">{{ $shop->name }}</span></div>@if ($shop->cover_url)<img src="{{ $shop->cover_url }}" alt="" class="mt-3 h-24 w-full rounded-xl object-cover">@else<div class="mt-3 flex h-24 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-center text-xs font-black text-white">{{ $shop->name }}</div>@endif<div class="mt-3 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[10px] text-slate-400">Buscar en {{ $shop->name }}...</div><div class="mt-3 grid grid-cols-2 gap-2">@foreach ($products->take(4) as $product)<div class="rounded-lg bg-white p-1.5 shadow-sm">@if ($product->primaryImage?->url)<img src="{{ $product->primaryImage->url }}" alt="" class="h-16 w-full rounded object-contain">@else<div class="h-16 rounded bg-slate-100"></div>@endif<p class="mt-1 truncate text-[9px] font-bold text-slate-700">{{ $product->name }}</p><p class="text-[9px] font-black text-blue-700">RD$ {{ number_format((float) $product->currentPrice(), 0) }}</p></div>@endforeach</div></div></div></section>
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-base font-black text-slate-950">Código QR</h2><a href="{{ route('seller.shops.qr.download', $shop) }}" class="text-xs font-black text-blue-700 hover:underline">Descargar</a></div><div class="mt-4 flex justify-center rounded-2xl bg-slate-50 p-4">{!! $qrSvg !!}</div><p class="mt-3 text-center text-xs leading-5 text-slate-500">Imprímelo en tu local para que tus clientes abran la tienda.</p></section>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.app>
