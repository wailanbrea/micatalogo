<x-layouts.app :title="'Menús de la tienda | ' . $shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
        ['label' => 'Menús de la tienda'],
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="menu-settings" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Administración de la tienda</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Menús visibles</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            Decide qué módulos estarán disponibles para esta tienda en la web y en la app Android. Ocultar un menú no borra datos ni operaciones anteriores; solo evita que se muestre o se abra.
                        </p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ count($enabledKeys) }} habilitados</span>
                </div>

                <form method="POST" action="{{ route('seller.shops.menus.update', $shop) }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')
                    @foreach ($groups as $group => $options)
                        <fieldset class="rounded-xl border border-slate-200 p-4">
                            <legend class="px-2 text-sm font-black text-slate-900">{{ $group }}</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($options as $option)
                                    @php($checked = in_array($option['key'], $enabledKeys, true) || $option['protected'])
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-3 transition hover:border-blue-200 hover:bg-blue-50/40">
                                        @if ($option['protected'])
                                            <input type="hidden" name="enabled_menu_keys[]" value="{{ $option['key'] }}">
                                        @endif
                                        <input
                                            type="checkbox"
                                            name="enabled_menu_keys[]"
                                            value="{{ $option['key'] }}"
                                            @checked($checked)
                                            @disabled($option['protected'])
                                            class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60"
                                        >
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold text-slate-800">{{ $option['label'] }}</span>
                                            @if ($option['protected'])
                                                <span class="mt-0.5 block text-[11px] font-semibold text-blue-600">Protegido para el owner</span>
                                            @else
                                                <span class="mt-0.5 block text-[11px] text-slate-500">Web y Android</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                        <p class="max-w-xl text-xs leading-5 text-slate-500">La configuración se aplica a todos los usuarios de esta tienda. Los permisos específicos de cada vendedor siguen siendo una segunda capa independiente.</p>
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700">Guardar menús</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</x-layouts.app>
