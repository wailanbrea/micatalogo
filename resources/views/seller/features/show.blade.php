<x-layouts.app :title="$feature['title'].' | '.$shop->name">
    <x-admin.header />

    <main class="min-h-screen bg-[#f7f7f6] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <x-seller.shop-header :shop="$shop" activeTab="feature" />

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-6 sm:px-8">
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">{{ $feature['group'] }}</p>
                    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-black tracking-tight text-slate-950">{{ $feature['title'] }}</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $feature['description'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            {{ $feature['status'] }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-5 p-6 sm:p-8 lg:grid-cols-[1.15fr_.85fr]">
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 sm:p-8">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-xl font-black text-blue-700">{{ strtoupper(substr($feature['title'], 0, 1)) }}</div>
                        <h2 class="mt-5 text-lg font-black text-slate-900">Estamos preparando este espacio</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">El menú ya está organizado y protegido por la misma cuenta y tienda. Aquí agregaremos el flujo completo, manteniendo los datos y las reglas contables de MiCatalogo.</p>
                        <div class="mt-6 flex items-center gap-3 text-xs font-bold text-slate-500">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">✓</span>
                            Acceso preparado
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-blue-700">2</span>
                            Contenido en desarrollo
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Puedes continuar con</p>
                        <div class="mt-4 space-y-2">
                            @foreach ($related as $item)
                                <a wire:navigate.hover href="{{ $item['url'] }}" class="group flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                    <span>{{ $item['label'] }}</span>
                                    <span class="text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-600">→</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</x-layouts.app>
