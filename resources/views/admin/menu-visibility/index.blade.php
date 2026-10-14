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

                <form method="GET" action="{{ route('admin.menus.index') }}" class="mt-6 grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem_auto]">
                    <label class="relative block">
                        <span class="sr-only">Buscar tienda o propietario</span>
                        <input type="search" name="q" value="{{ $search }}" placeholder="Buscar tienda, propietario o correo" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                    </label>
                    <label>
                        <span class="sr-only">Estado</span>
                        <select name="status" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                            <option value="all" @selected($status === 'all')>Todas</option>
                            <option value="active" @selected($status === 'active')>Activas</option>
                            <option value="suspended" @selected($status === 'suspended')>Suspendidas</option>
                        </select>
                    </label>
                    <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-700">Filtrar</button>
                </form>
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
