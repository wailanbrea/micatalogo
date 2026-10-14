<x-layouts.app title="Menús de todas las tiendas | MiCatalogo Admin">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Menús de todas las tiendas'],
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-rose-600">Owner · Administración global</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Menús por tienda</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Selecciona una tienda para decidir qué módulos verán sus usuarios en la web y en Android. Esta pantalla es exclusiva del owner y no cambia ni elimina datos.
                        </p>
                    </div>
                    <span class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700">{{ $shops->total() }} tiendas</span>
                </div>

                @php($hasFilters = $search !== '' || $status !== 'all')
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 sm:p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-blue-600 shadow-sm ring-1 ring-slate-200" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5h18M6 12h12m-8 7h4"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-black text-slate-900">Filtrar tiendas</p>
                                <p class="text-[11px] text-slate-500">Busca por tienda, propietario o correo</p>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-slate-500">{{ $shops->total() }} resultado{{ $shops->total() === 1 ? '' : 's' }}</span>
                    </div>

                    <form method="GET" action="{{ route('admin.menus.index') }}" class="mt-3 grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto_auto]">
                        <label class="group block">
                            <span class="sr-only">Buscar tienda o propietario</span>
                            <span class="flex h-12 w-full items-center rounded-xl border border-slate-200 bg-white transition focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10">
                                <span class="flex h-full w-12 shrink-0 items-center justify-center text-slate-400 transition group-focus-within:text-blue-600" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="m16 16 4.5 4.5"/></svg>
                                </span>
                                <input type="search" name="q" value="{{ $search }}" placeholder="Buscar tienda, propietario o correo" class="min-w-0 flex-1 bg-transparent pr-4 text-sm font-medium text-slate-800 outline-none placeholder:text-slate-400">
                            </span>
                        </label>

                        <fieldset class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1">
                            <legend class="sr-only">Filtrar por estado</legend>
                            @foreach (['all' => 'Todas', 'active' => 'Activas', 'suspended' => 'Suspendidas'] as $statusKey => $statusLabel)
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="{{ $statusKey }}" @checked($status === $statusKey) class="peer sr-only">
                                    <span class="inline-flex h-10 items-center rounded-lg px-3 text-xs font-bold transition peer-checked:bg-slate-900 peer-checked:text-white peer-checked:shadow-sm {{ $status === $statusKey ? '' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' }}">{{ $statusLabel }}</span>
                                </label>
                            @endforeach
                        </fieldset>

                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-black text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 xl:flex-none">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg>
                                Aplicar filtros
                            </button>
                            @if ($hasFilters)
                                <a href="{{ route('admin.menus.index') }}" class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-100" aria-label="Limpiar filtros">Limpiar</a>
                            @endif
                        </div>
                    </form>

                    @if ($hasFilters)
                        <div class="mt-3 flex flex-wrap items-center gap-2 px-1 text-xs">
                            <span class="font-bold text-slate-500">Filtros activos:</span>
                            @if ($search !== '')
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 font-bold text-blue-700">Búsqueda: {{ $search }}</span>
                            @endif
                            @if ($status !== 'all')
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2.5 py-1 font-bold text-slate-700">Estado: {{ $status === 'active' ? 'Activas' : 'Suspendidas' }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-[850px] w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Tienda</th>
                                <th class="px-5 py-4">Propietario</th>
                                <th class="px-5 py-4">Estado</th>
                                <th class="px-5 py-4">Datos</th>
                                <th class="px-5 py-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($shops as $shop)
                                @php($enabledCount = count(app(\App\Services\SellerMenuService::class)->enabledKeys($shop)))
                                <tr class="transition hover:bg-slate-50/80">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 font-black text-blue-700">{{ strtoupper(substr($shop->name, 0, 1)) }}</span>
                                            <div>
                                                <p class="font-black text-slate-900">{{ $shop->name }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">/tienda/{{ $shop->slug }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-800">{{ $shop->user?->name ?? 'Sin propietario' }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $shop->user?->email ?? '—' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $shop->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $shop->status === 'active' ? 'Activa' : 'Suspendida' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-xs text-slate-600">
                                        <p>{{ $enabledCount }} menús habilitados</p>
                                        <p class="mt-1">{{ $shop->products_count }} productos · {{ $shop->sellers_count }} vendedores</p>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('admin.menus.edit', $shop) }}" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-blue-700">Administrar menús</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">No encontramos tiendas con ese filtro.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $shops->links() }}</div>
            </section>
        </div>
    </main>
</x-layouts.app>
