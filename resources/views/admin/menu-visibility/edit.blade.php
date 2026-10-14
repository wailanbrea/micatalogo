<x-layouts.app title="Menús de tienda | MiCatalogo Admin">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Menús de todas las tiendas', 'url' => route('admin.menus.index')],
        ['label' => $shop->name],
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <a href="{{ route('admin.menus.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700">← Volver al listado</a>
                        <p class="mt-4 text-xs font-black uppercase tracking-[0.18em] text-rose-600">Owner · {{ $shop->name }}</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Administrar menús</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Estos módulos quedarán disponibles para todos los usuarios de esta tienda, sujetos a sus permisos individuales. La configuración se sincroniza con web y Android.</p>
                        <p class="mt-3 text-xs text-slate-500">Propietario: <span class="font-bold text-slate-700">{{ $shop->user?->email ?? 'Sin correo' }}</span></p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ count($enabledKeys) }} habilitados</span>
                </div>

                @if (session('status'))
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <form method="POST" action="{{ route('admin.menus.update', $shop) }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')
                    @foreach ($groups as $group => $options)
                        <fieldset class="rounded-xl border border-slate-200 p-4">
                            <legend class="px-2 text-sm font-black text-slate-900">{{ $group }}</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($options as $option)
                                    @php($checked = in_array($option['key'], $enabledKeys, true) || $option['protected'])
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-3 transition hover:border-blue-200 hover:bg-blue-50/40">
                                        @if ($option['protected'])<input type="hidden" name="enabled_menu_keys[]" value="{{ $option['key'] }}">@endif
                                        <input type="checkbox" name="enabled_menu_keys[]" value="{{ $option['key'] }}" @checked($checked) @disabled($option['protected']) class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:opacity-60">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold text-slate-800">{{ $option['label'] }}</span>
                                            @if ($option['protected'])
                                                <span class="mt-0.5 block text-[11px] font-semibold text-blue-600">Protegido para owner y administradores autorizados</span>
                                            @else
                                                <span class="mt-0.5 block text-[11px] text-slate-500">Web y Android · todos los usuarios de esta tienda</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                        <p class="max-w-xl text-xs leading-5 text-slate-500">Ocultar un menú no borra datos ni operaciones anteriores. El vendedor también debe tener el permiso individual correspondiente para verlo.</p>
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700">Guardar configuración</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</x-layouts.app>
