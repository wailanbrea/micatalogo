<x-layouts.app :title="$shop->exists ? 'Configuración | ' . $shop->name : 'Crear tienda | MiCatalogo'">
    @php
        $hoursForForm = app(\App\Services\ShopHoursService::class)->forForm(old('business_hours', $shop->business_hours));
        $hourLabels = \App\Services\ShopHoursService::DAYS;
        $operational = app(\App\Services\ShopOperationalSettingsService::class)->forShop($shop);
    @endphp
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="$shop->exists ? [
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Configuración']
        ] : [
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => 'Crear tienda']
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto {{ $shop->exists ? 'max-w-7xl' : 'max-w-2xl' }}">
            @if ($shop->exists)
                <!-- Shop Context & Local Navigation Tabs -->
                <x-seller.shop-header :shop="$shop" activeTab="settings" />
            @endif

            <section class="mx-auto w-full max-w-5xl rounded-xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <h1 class="text-2xl font-bold text-slate-900">{{ $shop->exists ? 'Edita tu tienda' : 'Crea tu tienda' }}</h1>
                <p class="mt-2 text-sm text-slate-600">Tu catalogo mostrara este nombre y enviara las consultas al WhatsApp indicado.</p>

                @if (session('status'))
                    <p class="mt-5 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
                @endif

                <form class="mt-6 space-y-5" method="POST" action="{{ $shop->exists ? route('seller.shops.update', $shop) : route('seller.shops.store') }}" enctype="multipart/form-data">
                    @csrf
                    @if ($shop->exists) @method('PUT') @endif
                    <div id="basic-information" class="scroll-mt-24 space-y-5">
                        <label class="text-sm font-medium text-slate-700" for="name">Nombre de la tienda</label>
                        <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="name" name="name" type="text" value="{{ old('name', $shop->name) }}" required autofocus>
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700" for="business_type">Tipo de negocio</label>
                        <select class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="business_type" name="business_type">
                            @foreach (app(\App\Services\BusinessProfileService::class)->types() as $type => $profile)
                                <option value="{{ $type }}" @selected(old('business_type', $shop->business_type ?: 'general_retail') === $type)>{{ $profile['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Preconfigura categorías y la presentación del panel. Cambiarlo agrega las nuevas categorías sin borrar las existentes.</p>
                        @error('business_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="address">Ubicación o dirección (opcional)</label>
                            <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="address" name="address" type="text" value="{{ old('address', $shop->address) }}" placeholder="Santo Domingo, RD">
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="maps_url">Enlace de Google Maps (opcional)</label>
                            <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="maps_url" name="maps_url" type="url" value="{{ old('maps_url', $shop->maps_url) }}" placeholder="https://maps.google.com/...">
                            @error('maps_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="slug">Enlace personalizado</label>
                        <div class="mt-1.5 flex min-w-0 rounded-lg shadow-sm"><span class="inline-flex shrink-0 items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">/tienda/</span><input class="block min-w-0 flex-1 rounded-r-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600" id="slug" name="slug" type="text" value="{{ old('slug', $shop->slug) }}" placeholder="mi-tienda"></div>
                        <p class="mt-1 text-xs text-slate-500">Si lo dejas vacio, lo generaremos con el nombre.</p>
                        @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div id="appearance" class="scroll-mt-24 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900">Identidad visual</p>
                                <p class="mt-0.5 text-xs text-slate-500">Estos colores se aplican a tu vitrina pública.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <input class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1" id="primary_color" name="primary_color" type="color" value="{{ old('primary_color', $shop->primary_color ?: '#1d4ed8') }}">
                                <input class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1" id="secondary_color" name="secondary_color" type="color" value="{{ old('secondary_color', $shop->secondary_color ?: '#0f172a') }}">
                            </div>
                        </div>
                        @error('primary_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('secondary_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Portada de la tienda -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                        <label class="block text-sm font-bold text-slate-800" for="cover">Imagen de portada</label>
                        <p class="mt-0.5 text-xs text-slate-500">Recomendado: una imagen horizontal. Se optimizará a WebP automáticamente.</p>
                        @if ($shop->exists && $shop->cover_url)
                            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <img src="{{ $shop->cover_url }}" alt="Portada {{ $shop->name }}" class="h-28 w-full object-cover">
                            </div>
                            <label class="mt-3 flex items-center gap-2 text-xs font-semibold text-rose-600 cursor-pointer hover:text-rose-800">
                                <input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                <span>Eliminar portada actual</span>
                            </label>
                        @endif
                        <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp,image/avif" class="mt-3 block w-full max-w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700 cursor-pointer">
                        @error('cover') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="description">Descripcion</label>
                        <textarea class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="description" name="description" rows="4">{{ old('description', $shop->description) }}</textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Logo de la tienda -->
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                        <label class="block text-sm font-bold text-slate-800" for="logo">Logo de la tienda</label>
                        <p class="mt-0.5 text-xs text-slate-500">Formato JPG, PNG o WebP. Se optimizará y convertirá a WebP automáticamente (400x400 px).</p>
                        
                        @if ($shop->exists && $shop->logo_url)
                            <div class="mt-3 flex items-center gap-4">
                                <img src="{{ $shop->logo_url }}" alt="Logo {{ $shop->name }}" class="h-16 w-16 rounded-xl object-cover border border-slate-200 shadow-xs">
                                <label class="flex items-center gap-2 text-xs font-semibold text-rose-600 cursor-pointer hover:text-rose-800">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <span>Eliminar logo actual</span>
                                </label>
                            </div>
                        @endif

                        <div class="mt-3">
                            <input 
                                type="file" 
                                id="logo" 
                                name="logo" 
                                accept="image/jpeg,image/png,image/webp,image/avif"
                                class="block w-full max-w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700 cursor-pointer"
                            >
                        </div>
                        @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div id="contact" class="scroll-mt-24 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="whatsapp_country_code">Codigo de pais</label>
                            <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="whatsapp_country_code" name="whatsapp_country_code" type="text" inputmode="numeric" value="{{ old('whatsapp_country_code', $shop->whatsapp_country_code ?: '1') }}" required>
                            @error('whatsapp_country_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="whatsapp_number">Numero de WhatsApp</label>
                            <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="whatsapp_number" name="whatsapp_number" type="tel" inputmode="numeric" value="{{ old('whatsapp_number', $shop->whatsapp_number) }}" required>
                            @error('whatsapp_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <section id="hours" x-data="{ copyMonday() { const mondayOpen = document.querySelector('[data-hours-open=\"monday\"]')?.value; const mondayClose = document.querySelector('[data-hours-close=\"monday\"]')?.value; ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday'].forEach(day => { const open = document.querySelector('[data-hours-open=\"' + day + '\"]'); const close = document.querySelector('[data-hours-close=\"' + day + '\"]'); if (open) open.value = mondayOpen; if (close) close.value = mondayClose; }); } }" class="scroll-mt-24 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-900">Horario</p>
                                <p class="mt-0.5 text-xs text-slate-500">Tu tienda puede mostrar cuándo estás abierto y cuándo pueden escribirte.</p>
                            </div>
                            <button type="button" @click="copyMonday()" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">Copiar el lunes de lunes a sábado</button>
                        </div>
                        <div class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white">
                            @foreach ($hoursForForm as $day => $dayHours)
                                <div class="grid gap-3 px-3 py-3 sm:grid-cols-[110px_minmax(0,1fr)_auto] sm:items-center">
                                    <span class="text-sm font-bold text-slate-800">{{ $hourLabels[$day] }}</span>
                                    <div class="grid grid-cols-2 gap-2 sm:max-w-sm">
                                        <label class="text-[11px] font-semibold text-slate-500">Abre
                                            <input data-hours-open="{{ $day }}" name="business_hours[{{ $day }}][open]" type="time" value="{{ old('business_hours.'.$day.'.open', $dayHours['open']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                                        </label>
                                        <label class="text-[11px] font-semibold text-slate-500">Cierra
                                            <input data-hours-close="{{ $day }}" name="business_hours[{{ $day }}][close]" type="time" value="{{ old('business_hours.'.$day.'.close', $dayHours['close']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                                        </label>
                                    </div>
                                    <div class="flex flex-wrap gap-3 text-xs text-slate-600">
                                        <label class="inline-flex items-center gap-1.5"><input name="business_hours[{{ $day }}][all_day]" type="checkbox" value="1" @checked(old('business_hours.'.$day.'.all_day', $dayHours['all_day'])) class="rounded border-slate-300 text-blue-600 focus:ring-blue-600"> 24 horas</label>
                                        <label class="inline-flex items-center gap-1.5"><input name="business_hours[{{ $day }}][closed]" type="checkbox" value="1" @checked(old('business_hours.'.$day.'.closed', $dayHours['closed'])) class="rounded border-slate-300 text-blue-600 focus:ring-blue-600"> Cerrado</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('business_hours') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </section>
                    <div id="google" class="scroll-mt-24">
                        <label class="text-sm font-medium text-slate-700" for="instagram">Instagram (opcional)</label>
                        <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="instagram" name="instagram" type="text" value="{{ old('instagram', $shop->instagram) }}" placeholder="mi.tienda">
                        @error('instagram') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-4 text-sm text-slate-700"><input name="offers_shipping" type="hidden" value="0"><input class="rounded border-slate-300 text-blue-600 focus:ring-blue-600" name="offers_shipping" type="checkbox" value="1" @checked(old('offers_shipping', $shop->offers_shipping))><span><strong class="block text-slate-900">Ofrecemos envio</strong>Indica si esta tienda puede enviar productos.</span></label>

                    <section id="operational-settings" class="scroll-mt-24 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900">Configuración operativa</p>
                            <p class="mt-0.5 text-xs text-slate-600">Moneda, impuestos, métodos de pago y módulos que también se sincronizan con la app MiCatalogo.</p>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="text-xs font-semibold text-slate-600">Moneda
                                <input name="operational_settings[currency]" maxlength="3" value="{{ old('operational_settings.currency', $operational['currency']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase text-slate-900">
                            </label>
                            <label class="text-xs font-semibold text-slate-600">Símbolo
                                <input name="operational_settings[currency_symbol]" maxlength="5" value="{{ old('operational_settings.currency_symbol', $operational['currency_symbol']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                            </label>
                            <label class="text-xs font-semibold text-slate-600">ITBIS (%)
                                <input name="operational_settings[tax_rate]" type="number" min="0" max="100" step="0.01" value="{{ old('operational_settings.tax_rate', $operational['tax_rate']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                            </label>
                            <label class="text-xs font-semibold text-slate-600">RNC
                                <input name="operational_settings[business_rnc]" value="{{ old('operational_settings.business_rnc', $operational['business_rnc']) }}" placeholder="Opcional" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                            </label>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[credit][enabled]" value="0"><input type="checkbox" name="operational_settings[credit][enabled]" value="1" @checked(old('operational_settings.credit.enabled', $operational['credit']['enabled'])) class="rounded border-slate-300 text-blue-600"> Permitir crédito</label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[wholesale][enabled]" value="0"><input type="checkbox" name="operational_settings[wholesale][enabled]" value="1" @checked(old('operational_settings.wholesale.enabled', $operational['wholesale']['enabled'])) class="rounded border-slate-300 text-blue-600"> Activar mayorista</label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[decants][enabled]" value="0"><input type="checkbox" name="operational_settings[decants][enabled]" value="1" @checked(old('operational_settings.decants.enabled', $operational['decants']['enabled'])) class="rounded border-slate-300 text-blue-600"> Activar decants</label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[images][auto_optimize]" value="0"><input type="checkbox" name="operational_settings[images][auto_optimize]" value="1" @checked(old('operational_settings.images.auto_optimize', $operational['images']['auto_optimize'])) class="rounded border-slate-300 text-blue-600"> Optimizar imágenes</label>
                        </div>

                        <div class="mt-5 grid gap-4 border-t border-blue-100 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="text-xs font-semibold text-slate-600">Zona horaria
                                <select name="operational_settings[timezone]" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900">
                                    @foreach (['America/Santo_Domingo' => 'Santo Domingo (UTC−04:00)', 'America/New_York' => 'Nueva York (UTC−05/04:00)', 'America/Mexico_City' => 'Ciudad de México (UTC−06/05:00)', 'America/Bogota' => 'Bogotá (UTC−05:00)', 'America/Panama' => 'Panamá (UTC−05:00)', 'Europe/Madrid' => 'Madrid (UTC+01/02:00)'] as $timezone => $label)
                                        <option value="{{ $timezone }}" @selected(old('operational_settings.timezone', $operational['timezone']) === $timezone)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-semibold text-slate-600">Días de crédito por defecto
                                <input name="operational_settings[credit][default_days]" type="number" inputmode="numeric" min="0" max="3650" step="1" value="{{ old('operational_settings.credit.default_days', $operational['credit']['default_days']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900">
                            </label>
                            <label class="text-xs font-semibold text-slate-600">Mínimo mayorista (unidades)
                                <input name="operational_settings[wholesale][minimum_quantity]" type="number" inputmode="numeric" min="1" max="100000" step="1" value="{{ old('operational_settings.wholesale.minimum_quantity', $operational['wholesale']['minimum_quantity']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900">
                            </label>
                            <label class="text-xs font-semibold text-slate-600">Imágenes por producto
                                <input name="operational_settings[images][max_per_product]" type="number" inputmode="numeric" min="1" max="20" step="1" value="{{ old('operational_settings.images.max_per_product', $operational['images']['max_per_product']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900">
                            </label>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[credit][allow_partial_payments]" value="0"><input type="checkbox" name="operational_settings[credit][allow_partial_payments]" value="1" @checked(old('operational_settings.credit.allow_partial_payments', $operational['credit']['allow_partial_payments'])) class="rounded border-slate-300 text-blue-600"> Permitir abonos</label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[orders][enabled]" value="0"><input type="checkbox" name="operational_settings[orders][enabled]" value="1" @checked(old('operational_settings.orders.enabled', $operational['orders']['enabled'])) class="rounded border-slate-300 text-blue-600"> Recibir pedidos online</label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[quick_service][enabled]" value="0"><input type="checkbox" name="operational_settings[quick_service][enabled]" value="1" @checked(old('operational_settings.quick_service.enabled', $operational['quick_service']['enabled'])) class="rounded border-slate-300 text-blue-600"> Servicio rápido</label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="operational_settings[recipes][enabled]" value="0"><input type="checkbox" name="operational_settings[recipes][enabled]" value="1" @checked(old('operational_settings.recipes.enabled', $operational['recipes']['enabled'])) class="rounded border-slate-300 text-blue-600"> Recetas / insumos</label>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-xs font-bold text-slate-800">Recibo</p>
                                <div class="mt-2 grid gap-2 text-xs text-slate-700">
                                    @foreach (['show_logo' => 'Mostrar logo', 'show_customer' => 'Mostrar cliente', 'show_seller' => 'Mostrar vendedor', 'show_notes' => 'Mostrar notas'] as $receiptKey => $receiptLabel)
                                        <label class="flex items-center gap-2"><input type="hidden" name="operational_settings[receipt][{{ $receiptKey }}]" value="0"><input type="checkbox" name="operational_settings[receipt][{{ $receiptKey }}]" value="1" @checked(old('operational_settings.receipt.' . $receiptKey, $operational['receipt'][$receiptKey])) class="rounded border-slate-300 text-blue-600"> {{ $receiptLabel }}</label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-xs font-bold text-slate-800">Comprobantes fiscales</p>
                                <label class="mt-2 flex items-center gap-2 text-xs text-slate-700"><input type="hidden" name="operational_settings[fiscal][enabled]" value="0"><input type="checkbox" name="operational_settings[fiscal][enabled]" value="1" @checked(old('operational_settings.fiscal.enabled', $operational['fiscal']['enabled'])) class="rounded border-slate-300 text-blue-600"> Activar comprobantes</label>
                                <select name="operational_settings[fiscal][invoice_type]" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900">
                                    @foreach (['consumer' => 'Consumidor final', 'credit' => 'Crédito fiscal', 'special' => 'Régimen especial'] as $invoiceType => $invoiceLabel)
                                        <option value="{{ $invoiceType }}" @selected(old('operational_settings.fiscal.invoice_type', $operational['fiscal']['invoice_type']) === $invoiceType)>{{ $invoiceLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-xs font-bold text-slate-800">Compras y recepción</p>
                                <div class="mt-2 grid gap-2 text-xs text-slate-700">
                                    <label class="flex items-center gap-2"><input type="hidden" name="operational_settings[purchases][allow_partial_receive]" value="0"><input type="checkbox" name="operational_settings[purchases][allow_partial_receive]" value="1" @checked(old('operational_settings.purchases.allow_partial_receive', $operational['purchases']['allow_partial_receive'])) class="rounded border-slate-300 text-blue-600"> Permitir recepción parcial</label>
                                    <label class="flex items-center gap-2"><input type="hidden" name="operational_settings[purchases][require_supplier]" value="0"><input type="checkbox" name="operational_settings[purchases][require_supplier]" value="1" @checked(old('operational_settings.purchases.require_supplier', $operational['purchases']['require_supplier'])) class="rounded border-slate-300 text-blue-600"> Exigir suplidor</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-xs font-bold text-slate-800">Envíos</p>
                                <label class="mt-2 flex items-center gap-2 text-xs text-slate-700"><input type="hidden" name="operational_settings[shipping][enabled]" value="0"><input type="checkbox" name="operational_settings[shipping][enabled]" value="1" @checked(old('operational_settings.shipping.enabled', $operational['shipping']['enabled'])) class="rounded border-slate-300 text-blue-600"> Activar tipos de entrega</label>
                                <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-700">
                                    @foreach (['pickup' => 'Recoger', 'delivery' => 'Entrega'] as $shippingType => $shippingLabel)
                                        <label class="flex items-center gap-2"><input type="checkbox" name="operational_settings[shipping][types][]" value="{{ $shippingType }}" @checked(in_array($shippingType, old('operational_settings.shipping.types', $operational['shipping']['types']), true)) class="rounded border-slate-300 text-blue-600"> {{ $shippingLabel }}</label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-xs font-bold text-slate-800">Decants: tamaños predeterminados (ml)</p>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    @foreach (array_pad(array_values(old('operational_settings.decants.default_ml', $operational['decants']['default_ml'])), 3, '') as $ml)
                                        <input name="operational_settings[decants][default_ml][]" type="number" inputmode="numeric" min="1" max="10000" step="1" value="{{ $ml }}" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs text-slate-900" aria-label="Tamaño de decant en mililitros">
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-xs font-bold text-slate-800">Métodos de pago visibles</p>
                                <div class="mt-2 grid gap-2 text-xs text-slate-700">
                                    @foreach (config('catalog.payment_methods', []) as $methodKey => $method)
                                        <label class="flex items-center gap-2"><input type="checkbox" name="operational_settings[payment_methods][]" value="{{ $methodKey }}" @checked(in_array($methodKey, old('operational_settings.payment_methods', $operational['payment_methods']), true)) class="rounded border-slate-300 text-blue-600"> {{ $method['label'] }}</label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-slate-500">Efectivo permanece siempre disponible para que el cierre de caja sea consistente. Los valores numéricos aceptan únicamente cantidades enteras o decimales según el campo.</p>
                    </section>

                    @if ($shop->exists && auth()->user()?->isAdmin())
                        <section class="rounded-xl border border-rose-200 bg-rose-50/50 p-4">
                            <div>
                                <p class="text-sm font-bold text-slate-900">Controles del owner</p>
                                <p class="mt-0.5 text-xs text-slate-600">Estos campos solo aparecen para el owner del sistema.</p>
                            </div>
                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="text-sm font-medium text-slate-700" for="status">Estado</label>
                                    <select class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="status" name="status">
                                        <option value="active" @selected(old('status', $shop->status) === 'active')>Activa</option>
                                        <option value="suspended" @selected(old('status', $shop->status) === 'suspended')>Suspendida</option>
                                    </select>
                                    @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-700" for="product_limit">Límite de productos</label>
                                    <input class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="product_limit" name="product_limit" type="number" min="0" max="1000000" value="{{ old('product_limit', $shop->product_limit) }}" placeholder="Según el plan">
                                    @error('product_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <label class="flex items-center gap-3 rounded-md border border-rose-200 bg-white px-3 py-2.5 text-sm text-slate-700 sm:mt-6">
                                    <input name="discovery_enabled" type="hidden" value="0">
                                    <input class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" name="discovery_enabled" type="checkbox" value="1" @checked(old('discovery_enabled', $shop->discovery_enabled))>
                                    <span><strong class="block text-slate-900">Descubrimiento público</strong><span class="text-xs text-slate-500">Permitir mostrarla en búsquedas.</span></span>
                                </label>
                            </div>
                            @error('discovery_enabled') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </section>
                    @endif

                    @if ($shop->exists)
                        <a class="inline-block text-sm font-semibold text-blue-700 hover:underline" href="{{ route('seller.shops.categories.index', $shop) }}">Gestionar categorias internas →</a>
                    @endif
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button class="rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" type="submit">{{ $shop->exists ? 'Guardar cambios' : 'Crear tienda' }}</button>
                        @if ($shop->exists)
                            <button class="text-sm font-semibold text-red-700 hover:text-red-800" type="submit" form="delete-shop">Eliminar tienda</button>
                        @endif
                    </div>
                </form>
                @if ($shop->exists)
                    <section id="payment-accounts" class="mt-6 rounded-xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-900">Cuentas de pago</p>
                                <p class="mt-0.5 text-xs text-slate-600">Cuentas que puedes mostrar al cliente cuando pague por transferencia bancaria.</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black text-blue-700">{{ ($paymentAccounts ?? collect())->where('is_active', true)->count() }} activas</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            @forelse (($paymentAccounts ?? collect())->where('is_active', true) as $account)
                                <form method="POST" action="{{ route('seller.shops.payment-accounts.update', [$shop, 'paymentAccount' => $account]) }}" class="rounded-xl border border-slate-200 bg-white p-4">
                                    @csrf @method('PUT')
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <label class="text-xs font-bold text-slate-600">Nombre visible<input required name="name" value="{{ $account->name }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                                        <label class="text-xs font-bold text-slate-600">Banco<input name="bank_name" value="{{ $account->bank_name }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                                        <label class="text-xs font-bold text-slate-600">Número de cuenta<input name="account_number" value="{{ $account->account_number }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                                        <label class="text-xs font-bold text-slate-600">Titular<input name="account_holder" value="{{ $account->account_holder }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                                    </div>
                                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <label class="min-w-0 flex-1 text-xs font-bold text-slate-600">Instrucciones para el cliente<textarea name="instructions" rows="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">{{ $account->instructions }}</textarea></label>
                                        <div class="flex gap-2"><button class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-black text-white hover:bg-blue-700">Guardar</button></div>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('seller.shops.payment-accounts.destroy', [$shop, 'paymentAccount' => $account]) }}" class="-mt-12 mr-3 flex justify-end"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE"><button class="rounded-lg px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50">Desactivar</button></form>
                            @empty
                                <p class="rounded-xl border border-dashed border-blue-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Aún no has agregado cuentas de pago.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('seller.shops.payment-accounts.store', $shop) }}" class="mt-4 rounded-xl border border-dashed border-blue-200 bg-white p-4">
                            @csrf
                            <p class="text-xs font-black uppercase tracking-wide text-blue-800">Agregar cuenta</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <input required name="name" placeholder="Ej. Cuenta Popular" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input name="bank_name" placeholder="Banco" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input name="account_number" placeholder="Número de cuenta" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input name="account_holder" placeholder="Titular" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row"><textarea name="instructions" rows="1" placeholder="Instrucciones opcionales" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><button class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700">Agregar cuenta</button></div>
                        </form>
                    </section>
                @endif
                @if ($shop->exists)
                    <form id="delete-shop" method="POST" action="{{ route('seller.shops.destroy', $shop) }}">@csrf @method('DELETE')</form>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
