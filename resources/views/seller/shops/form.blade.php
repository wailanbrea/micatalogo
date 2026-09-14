<x-layouts.app :title="$shop->exists ? 'Configuración | ' . $shop->name : 'Crear tienda | MiCatalogo'">
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

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <h1 class="text-2xl font-bold text-slate-900">{{ $shop->exists ? 'Edita tu tienda' : 'Crea tu tienda' }}</h1>
                <p class="mt-2 text-sm text-slate-600">Tu catalogo mostrara este nombre y enviara las consultas al WhatsApp indicado.</p>

                @if (session('status'))
                    <p class="mt-5 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
                @endif

                <form class="mt-6 space-y-5" method="POST" action="{{ $shop->exists ? route('seller.shops.update', $shop) : route('seller.shops.store') }}" enctype="multipart/form-data">
                    @csrf
                    @if ($shop->exists) @method('PUT') @endif
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="name">Nombre de la tienda</label>
                        <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="name" name="name" type="text" value="{{ old('name', $shop->name) }}" required autofocus>
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="slug">Enlace personalizado</label>
                        <div class="mt-1.5 flex rounded-md shadow-sm"><span class="inline-flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">/tienda/</span><input class="block min-w-0 flex-1 rounded-r-md border-slate-300 focus:border-blue-600 focus:ring-blue-600" id="slug" name="slug" type="text" value="{{ old('slug', $shop->slug) }}" placeholder="mi-tienda"></div>
                        <p class="mt-1 text-xs text-slate-500">Si lo dejas vacio, lo generaremos con el nombre.</p>
                        @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="description">Descripcion</label>
                        <textarea class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="description" name="description" rows="4">{{ old('description', $shop->description) }}</textarea>
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
                                class="text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700 cursor-pointer"
                            >
                        </div>
                        @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="whatsapp_country_code">Codigo de pais</label>
                            <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="whatsapp_country_code" name="whatsapp_country_code" type="text" inputmode="numeric" value="{{ old('whatsapp_country_code', $shop->whatsapp_country_code ?: '1') }}" required>
                            @error('whatsapp_country_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="whatsapp_number">Numero de WhatsApp</label>
                            <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="whatsapp_number" name="whatsapp_number" type="tel" inputmode="numeric" value="{{ old('whatsapp_number', $shop->whatsapp_number) }}" required>
                            @error('whatsapp_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="instagram">Instagram (opcional)</label>
                        <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="instagram" name="instagram" type="text" value="{{ old('instagram', $shop->instagram) }}" placeholder="mi.tienda">
                        @error('instagram') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-3 rounded-md border border-slate-200 p-4 text-sm text-slate-700"><input name="offers_shipping" type="hidden" value="0"><input class="rounded border-slate-300 text-blue-600 focus:ring-blue-600" name="offers_shipping" type="checkbox" value="1" @checked(old('offers_shipping', $shop->offers_shipping))><span><strong class="block text-slate-900">Ofrecemos envio</strong>Indica si esta tienda puede enviar productos.</span></label>
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
                    <form id="delete-shop" method="POST" action="{{ route('seller.shops.destroy', $shop) }}">@csrf @method('DELETE')</form>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
