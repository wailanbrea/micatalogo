<x-layouts.app :title="'Mi tienda | '.$shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Catálogo'],
        ['label' => 'Mi tienda'],
    ]" />

    <main class="min-h-screen bg-[#f7f7f6] px-4 py-6 sm:px-6 lg:px-8">
        @php
            $storefrontHidden = [
                'slug' => $shop->slug,
                'business_type' => $shop->business_type,
                'whatsapp_country_code' => $shop->whatsapp_country_code ?: '1',
                'whatsapp_number' => $shop->whatsapp_number,
                'instagram' => $shop->instagram,
                'offers_shipping' => $shop->offers_shipping ? '1' : '0',
                'address' => $shop->address,
                'maps_url' => $shop->maps_url,
                'description' => $shop->description,
                'primary_color' => $shop->primary_color ?: '#2563EB',
                'secondary_color' => $shop->secondary_color ?: '#0F172A',
            ];
        @endphp
        <div class="mx-auto max-w-[1600px] space-y-5">
            <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">Catálogo / Mi tienda</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Mi tienda</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Todo lo de tu tienda en línea: compártela, decide cómo se ve y mira lo que le falta.</p>
                </div>
                <form method="POST" action="{{ route('seller.shops.update', $shop) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    @csrf @method('PUT')
                    <input type="hidden" name="return_to" value="storefront">
                    <input type="hidden" name="return_tab" value="{{ $activeTab }}">
                    @foreach ($storefrontHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <label class="text-xs font-black text-slate-600" for="storefront-shop-name">Nombre de la tienda
                        <input id="storefront-shop-name" name="name" value="{{ $shop->name }}" required maxlength="120" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                        <span class="mt-1 block text-[11px] font-normal text-slate-500">Lo ven tus clientes en la tienda, recibos y mensajes. El enlace no cambia: {{ $shopUrl }}</span>
                    </label>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-blue-700">Guardar nombre</button>
                </form>
                <div class="flex justify-end">
                    <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">Ver mi tienda <span aria-hidden="true">↗</span></a>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-5">
                    <nav class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm" aria-label="Secciones de Mi tienda">
                        @php
                            $storefrontTabs = [
                                'resumen' => ['label' => 'Resumen'],
                                'apariencia' => ['label' => 'Apariencia'],
                                'contacto' => ['label' => 'Contacto y horario'],
                                'catalogo' => ['label' => 'Catálogo'],
                                'vitrinas' => ['label' => 'Vitrinas'],
                                'anuncios' => ['label' => 'Anuncios'],
                                'google' => ['label' => 'Google'],
                            ];
                        @endphp
                        @foreach ($storefrontTabs as $anchor => $tab)
                            <a href="{{ route('seller.shops.storefront', [$shop, 'tab' => $anchor]) }}" class="shrink-0 rounded-xl px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700 {{ $activeTab === $anchor ? 'bg-blue-600 text-white hover:bg-blue-600 hover:text-white' : '' }}" @if ($activeTab === $anchor) aria-current="page" @endif>{{ $tab['label'] }}</a>
                        @endforeach
                    </nav>

                    @if ($activeTab !== 'resumen')
                        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            @if ($activeTab === 'apariencia')
                                <div class="flex flex-col gap-2 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Apariencia</p><h2 class="mt-2 text-xl font-black text-slate-950">Haz que tu tienda se reconozca</h2><p class="mt-1 text-sm leading-6 text-slate-500">Logo, colores, portada y descripción aparecen en la vitrina pública y en los recibos.</p></div>
                                    <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-black text-blue-700">Vista previa activa</span>
                                </div>
                                <form x-data="{ primary: @js($shop->primary_color ?: '#2563EB'), secondary: @js($shop->secondary_color ?: '#0F172A'), palettes: [{ name: 'Azul MiCatalogo', primary: '#2563EB', secondary: '#0F172A' }, { name: 'Esmeralda', primary: '#059669', secondary: '#064E3B' }, { name: 'Violeta', primary: '#7C3AED', secondary: '#312E81' }, { name: 'Coral', primary: '#EA580C', secondary: '#7C2D12' }] }" class="mt-5 space-y-5" method="POST" action="{{ route('seller.shops.update', $shop) }}" enctype="multipart/form-data">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="return_to" value="storefront"><input type="hidden" name="return_tab" value="apariencia">
                                    <input type="hidden" name="name" value="{{ $shop->name }}">@foreach ($storefrontHidden as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                                        <div class="space-y-4">
                                            <div>
                                                <p class="text-xs font-bold text-slate-600">Paleta rápida</p>
                                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                    <template x-for="palette in palettes" :key="palette.name">
                                                        <button type="button" @click="primary = palette.primary; secondary = palette.secondary" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left transition hover:border-blue-300 hover:bg-blue-50">
                                                            <span class="flex -space-x-1.5"><span class="h-6 w-6 rounded-full border-2 border-white" :style="`background:${palette.primary}`"></span><span class="h-6 w-6 rounded-full border-2 border-white" :style="`background:${palette.secondary}`"></span></span>
                                                            <span class="text-xs font-black text-slate-700" x-text="palette.name"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <label class="text-xs font-bold text-slate-600">Color principal
                                                    <span class="mt-2 flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-2"><input name="primary_color" x-model="primary" type="color" class="h-8 w-10 cursor-pointer rounded-lg border-0 bg-transparent p-0"><input x-model="primary" maxlength="7" pattern="#[0-9A-Fa-f]{6}" class="min-w-0 flex-1 border-0 bg-transparent px-1 text-sm font-bold uppercase text-slate-800 outline-none"></span>
                                                </label>
                                                <label class="text-xs font-bold text-slate-600">Color secundario
                                                    <span class="mt-2 flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-2"><input name="secondary_color" x-model="secondary" type="color" class="h-8 w-10 cursor-pointer rounded-lg border-0 bg-transparent p-0"><input x-model="secondary" maxlength="7" pattern="#[0-9A-Fa-f]{6}" class="min-w-0 flex-1 border-0 bg-transparent px-1 text-sm font-bold uppercase text-slate-800 outline-none"></span>
                                                </label>
                                            </div>
                                            <p class="text-[11px] leading-5 text-slate-500">Elige una paleta para empezar o escribe un código hexadecimal. La vista previa se actualiza al instante; los cambios se aplican al guardar.</p>
                                        </div>
                                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Vista previa</p>
                                            <div class="mt-3 rounded-xl bg-white p-3 shadow-sm">
                                                <div class="h-16 rounded-lg" :style="`background: linear-gradient(135deg, ${primary}, ${secondary})`"></div>
                                                <p class="mt-3 truncate text-sm font-black text-slate-900">{{ $shop->name }}</p>
                                                <div class="mt-3 flex gap-2"><span class="rounded-lg px-3 py-2 text-[10px] font-black text-white" :style="`background:${primary}`">Ver catálogo</span><span class="rounded-lg border px-3 py-2 text-[10px] font-black" :style="`border-color:${primary}; color:${primary}`">WhatsApp</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <label class="block text-xs font-bold text-slate-600">Descripción de tu negocio<textarea name="description" rows="4" maxlength="2000" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">{{ $shop->description }}</textarea></label>
                                    <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-600">Logo<input name="logo" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="mt-2 block w-full rounded-xl border border-slate-200 p-2 text-xs"></label><label class="text-xs font-bold text-slate-600">Portada<input name="cover" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="mt-2 block w-full rounded-xl border border-slate-200 p-2 text-xs"></label></div>
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4"><p class="text-xs leading-5 text-slate-500">Los cambios se guardan sin salir de Mi tienda y actualizan la vista previa.</p><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700" type="submit">Guardar apariencia</button></div>
                                </form>
                            @elseif ($activeTab === 'contacto')
                                <div class="border-b border-slate-100 pb-5"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Contacto y horario</p><h2 class="mt-2 text-xl font-black text-slate-950">Dile al cliente dónde encontrarte</h2><p class="mt-1 text-sm leading-6 text-slate-500">WhatsApp, mapa, dirección, redes y horario quedan visibles en tu vitrina.</p></div>
                                <form class="mt-5 space-y-5" method="POST" action="{{ route('seller.shops.update', $shop) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="return_to" value="storefront"><input type="hidden" name="return_tab" value="contacto">
                                    <input type="hidden" name="name" value="{{ $shop->name }}">@foreach ($storefrontHidden as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                    <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-600">Código de país<input name="whatsapp_country_code" value="{{ $shop->whatsapp_country_code ?: '1' }}" inputmode="numeric" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></label><label class="text-xs font-bold text-slate-600">WhatsApp del negocio<input name="whatsapp_number" value="{{ $shop->whatsapp_number }}" inputmode="numeric" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></label></div>
                                    <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-600">Dirección<input name="address" value="{{ $shop->address }}" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></label><label class="text-xs font-bold text-slate-600">Enlace de Google Maps<input name="maps_url" value="{{ $shop->maps_url }}" type="url" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></label></div>
                                    <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-600">Instagram<input name="instagram" value="{{ $shop->instagram }}" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></label><label class="text-xs font-bold text-slate-600">Descripción<textarea name="description" rows="2" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">{{ $shop->description }}</textarea></label></div>
                                    <div class="overflow-hidden rounded-2xl border border-slate-200"><div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 p-4"><div><p class="text-sm font-black text-slate-900">Horario</p><p class="mt-1 text-xs text-slate-500">Copia el horario del lunes o ajusta cada día.</p></div><button type="button" x-data x-on:click="const o=document.querySelector('[data-hours-open=monday]')?.value,c=document.querySelector('[data-hours-close=monday]')?.value;['tuesday','wednesday','thursday','friday','saturday'].forEach(d=>{const a=document.querySelector('[data-hours-open='+d+']'),b=document.querySelector('[data-hours-close='+d+']');if(a)a.value=o;if(b)b.value=c})" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">Copiar lunes</button></div><div class="divide-y divide-slate-100">@foreach ($hoursForForm as $day => $dayHours)<div class="grid gap-2 p-3 sm:grid-cols-[100px_1fr_auto] sm:items-center"><span class="text-xs font-bold text-slate-800">{{ \App\Services\ShopHoursService::DAYS[$day] }}</span><div class="grid grid-cols-2 gap-2"><input data-hours-open="{{ $day }}" name="business_hours[{{ $day }}][open]" type="time" value="{{ $dayHours['open'] }}" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"><input data-hours-close="{{ $day }}" name="business_hours[{{ $day }}][close]" type="time" value="{{ $dayHours['close'] }}" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"></div><label class="text-xs text-slate-600"><input name="business_hours[{{ $day }}][closed]" type="checkbox" value="1" @checked($dayHours['closed'])> Cerrado</label><input name="business_hours[{{ $day }}][all_day]" type="hidden" value="0"></div>@endforeach</div></div>
                                    <div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700" type="submit">Guardar contacto y horario</button></div>
                                </form>
                            @elseif ($activeTab === 'catalogo')
                                <div class="border-b border-slate-100 pb-5"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Catálogo</p><h2 class="mt-2 text-xl font-black text-slate-950">Cómo ven tus productos</h2><p class="mt-1 text-sm leading-6 text-slate-500">Los agotados se mantienen al final y puedes controlar si se ven las existencias.</p></div>
                                <form class="mt-5 space-y-4" method="POST" action="{{ route('seller.shops.update', $shop) }}">@csrf @method('PUT')<input type="hidden" name="return_to" value="storefront"><input type="hidden" name="return_tab" value="catalogo"><input type="hidden" name="name" value="{{ $shop->name }}">@foreach ($storefrontHidden as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                    <label class="block text-xs font-bold text-slate-600">Orden inicial<select name="operational_settings[catalog][sort]" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="name_asc" @selected($operational['catalog']['sort'] === 'name_asc')>Nombre A–Z</option><option value="recent" @selected($operational['catalog']['sort'] === 'recent')>Más recientes</option><option value="price_asc" @selected($operational['catalog']['sort'] === 'price_asc')>Más baratos</option><option value="price_desc" @selected($operational['catalog']['sort'] === 'price_desc')>Más caros</option></select></label>
                                    @foreach (['offers_first' => 'Ofertas primero', 'hide_out_of_stock' => 'Ocultar productos agotados', 'show_stock' => 'Mostrar cuántas quedan', 'allow_backorder' => 'Vender por encargo'] as $key => $label)<label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm"><input type="hidden" name="operational_settings[catalog][{{ $key }}]" value="0"><input type="checkbox" name="operational_settings[catalog][{{ $key }}]" value="1" @checked($operational['catalog'][$key]) class="mt-0.5 rounded border-slate-300 text-blue-600"><span><span class="block font-bold text-slate-800">{{ $label }}</span><span class="mt-1 block text-xs text-slate-500">Esta preferencia se aplica a la vitrina pública.</span></span></label>@endforeach
                                    <div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700" type="submit">Guardar catálogo</button></div>
                                </form>
                            @elseif ($activeTab === 'vitrinas')
                                <div class="border-b border-slate-100 pb-5"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Vitrinas</p><h2 class="mt-2 text-xl font-black text-slate-950">Activa las experiencias que ofreces</h2><p class="mt-1 text-sm leading-6 text-slate-500">Mayorista y decants usan el mismo catálogo, pero muestran una entrada adaptada al cliente.</p></div>
                                <form class="mt-5 space-y-4" method="POST" action="{{ route('seller.shops.update', $shop) }}">@csrf @method('PUT')<input type="hidden" name="return_to" value="storefront"><input type="hidden" name="return_tab" value="vitrinas"><input type="hidden" name="name" value="{{ $shop->name }}">@foreach ($storefrontHidden as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4"><input type="hidden" name="operational_settings[wholesale][enabled]" value="0"><input type="checkbox" name="operational_settings[wholesale][enabled]" value="1" @checked($operational['wholesale']['enabled']) class="mt-1 rounded border-slate-300 text-blue-600"><span><span class="block text-sm font-black text-slate-800">Tienda mayorista</span><span class="mt-1 block text-xs leading-5 text-slate-500">Crea una entrada con precios mayoristas y mínimo de unidades.</span></span></label>
                                    <label class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50/40 p-4"><input type="hidden" name="operational_settings[decants][enabled]" value="0"><input type="checkbox" name="operational_settings[decants][enabled]" value="1" @checked($operational['decants']['enabled']) class="mt-1 rounded border-slate-300 text-blue-600"><span><span class="block text-sm font-black text-slate-800">Vendo decants en mi tienda</span><span class="mt-1 block text-xs leading-5 text-slate-500">Muestra la pestaña Decants y ofrece esa presentación cuando la botella no esté disponible.</span></span></label>
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4"><input type="hidden" name="operational_settings[decants][as_cover]" value="0"><input type="checkbox" name="operational_settings[decants][as_cover]" value="1" @checked($operational['decants']['as_cover']) class="mt-1 rounded border-slate-300 text-blue-600"><span><span class="block text-sm font-black text-slate-800">Decants como portada</span><span class="mt-1 block text-xs leading-5 text-slate-500">El enlace principal abre directamente la vitrina de decants.</span></span></label>
                                    <label class="block text-xs font-bold text-slate-600">Texto de tu sección de decants<textarea name="operational_settings[decants][section_text]" rows="3" maxlength="500" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">{{ $operational['decants']['section_text'] }}</textarea></label><div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700" type="submit">Guardar vitrinas</button></div>
                                </form>
                            @elseif ($activeTab === 'anuncios')
                                <div class="border-b border-slate-100 pb-5"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Anuncios</p><h2 class="mt-2 text-xl font-black text-slate-950">Mide tus anuncios</h2><p class="mt-1 text-sm leading-6 text-slate-500">Los píxeles se cargarán solo después de que el cliente acepte las cookies; tus reportes internos siguen contando igual.</p></div>
                                <form class="mt-5 space-y-4" method="POST" action="{{ route('seller.shops.update', $shop) }}">@csrf @method('PUT')<input type="hidden" name="return_to" value="storefront"><input type="hidden" name="return_tab" value="anuncios"><input type="hidden" name="name" value="{{ $shop->name }}">@foreach ($storefrontHidden as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                    @foreach (['meta_pixel' => 'Meta Pixel (Instagram y Facebook)', 'tiktok_pixel' => 'TikTok Pixel', 'ga4' => 'Google Analytics (GA4)'] as $key => $label)<label class="block text-xs font-bold text-slate-600">{{ $label }}<input name="operational_settings[marketing][{{ $key }}]" value="{{ $operational['marketing'][$key] }}" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="Opcional"></label>@endforeach
                                    <div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700" type="submit">Guardar anuncios</button></div>
                                </form>
                            @elseif ($activeTab === 'google')
                                <div class="border-b border-slate-100 pb-5"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Google</p><h2 class="mt-2 text-xl font-black text-slate-950">Qué tan fácil te encuentra Google</h2><p class="mt-1 text-sm leading-6 text-slate-500">Completa la información pública de tu negocio y verifica tu dominio cuando estés listo.</p></div>
                                <div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="rounded-2xl bg-slate-50 p-4"><p class="text-2xl font-black text-blue-700">{{ $withoutPhotoCount }}</p><p class="mt-1 text-xs text-slate-600">productos sin foto</p></div><div class="rounded-2xl bg-slate-50 p-4"><p class="text-2xl font-black text-blue-700">{{ $productCount - $publishedCount }}</p><p class="mt-1 text-xs text-slate-600">productos por publicar</p></div><div class="rounded-2xl bg-slate-50 p-4"><p class="text-2xl font-black text-blue-700">{{ filled($shop->description) ? '✓' : '—' }}</p><p class="mt-1 text-xs text-slate-600">descripción lista</p></div></div>
                                <form class="mt-5 space-y-4" method="POST" action="{{ route('seller.shops.update', $shop) }}">@csrf @method('PUT')<input type="hidden" name="return_to" value="storefront"><input type="hidden" name="return_tab" value="google"><input type="hidden" name="name" value="{{ $shop->name }}">@foreach ($storefrontHidden as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach<label class="block text-xs font-bold text-slate-600">Etiqueta de Google Search Console<input name="operational_settings[marketing][google_site_verification]" value="{{ $operational['marketing']['google_site_verification'] }}" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-sm" placeholder="<meta name=...>"></label><div class="rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-600">Entra a Search Console, agrega tu tienda como prefijo de URL y pega aquí la etiqueta HTML completa.</div><div class="flex justify-end"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700" type="submit">Guardar Google</button></div></form>
                            @endif
                        </section>
                    @endif

                    @if ($activeTab === 'resumen')

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
                        <div class="mt-4 flex flex-wrap gap-2 text-xs"><a href="{{ route('seller.shops.edit', $shop) }}#appearance" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:border-blue-200 hover:bg-blue-50">Apariencia avanzada</a><a href="{{ route('seller.shops.edit', $shop) }}#contact" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:border-blue-200 hover:bg-blue-50">Contacto</a><a href="{{ route('seller.shops.edit', $shop) }}#hours" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:border-blue-200 hover:bg-blue-50">Horario</a><a href="{{ route('seller.shops.edit', $shop) }}#google" class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:border-blue-200 hover:bg-blue-50">Redes y Google</a></div>
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
                    @endif
                </div>

                <aside class="space-y-5 xl:sticky xl:top-20 xl:self-start">
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600">Vista previa</p><h2 class="mt-1 text-lg font-black text-slate-950">Así la ve tu cliente</h2></div><span class="text-xs font-bold text-emerald-600">Actualizada</span></div><p class="mt-2 text-xs leading-5 text-slate-500">Tus cambios aparecen en la vitrina pública al guardar.</p><div class="mx-auto mt-5 max-w-[260px] overflow-hidden rounded-[2rem] border-8 border-slate-900 bg-white shadow-xl"><div class="h-5 bg-slate-900"></div><div class="bg-slate-50 p-3"><div class="flex items-center gap-2">@if ($shop->logo_url)<img src="{{ $shop->logo_url }}" alt="" class="h-7 w-7 rounded-lg object-cover">@else<span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-600 text-[10px] font-black text-white">{{ strtoupper(substr($shop->name, 0, 1)) }}</span>@endif<span class="truncate text-[11px] font-black text-slate-800">{{ $shop->name }}</span></div>@if ($shop->cover_url)<img src="{{ $shop->cover_url }}" alt="" class="mt-3 h-24 w-full rounded-xl object-cover">@else<div class="mt-3 flex h-24 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 text-center text-xs font-black text-white">{{ $shop->name }}</div>@endif<div class="mt-3 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[10px] text-slate-400">Buscar en {{ $shop->name }}...</div><div class="mt-3 grid grid-cols-2 gap-2">@foreach ($products->take(4) as $product)<div class="rounded-lg bg-white p-1.5 shadow-sm">@if ($product->primaryImage?->url)<img src="{{ $product->primaryImage->url }}" alt="" class="h-16 w-full rounded object-contain">@else<div class="h-16 rounded bg-slate-100"></div>@endif<p class="mt-1 truncate text-[9px] font-bold text-slate-700">{{ $product->name }}</p><p class="text-[9px] font-black text-blue-700">RD$ {{ number_format((float) $product->currentPrice(), 0) }}</p></div>@endforeach</div></div></div></section>
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-base font-black text-slate-950">Código QR</h2><a href="{{ route('seller.shops.qr.download', $shop) }}" class="text-xs font-black text-blue-700 hover:underline">Descargar</a></div><div class="mt-4 flex justify-center rounded-2xl bg-slate-50 p-4">{!! $qrSvg !!}</div><p class="mt-3 text-center text-xs leading-5 text-slate-500">Imprímelo en tu local para que tus clientes abran la tienda.</p></section>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.app>
