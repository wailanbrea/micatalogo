<x-layouts.app :title="'Vendedores | ' . $shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
        ['label' => 'Vendedores'],
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="sellers" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                    <div class="border-b border-slate-100 pb-5"><h1 class="text-xl font-bold text-slate-900">Vendedores y comisiones</h1><p class="mt-1 text-xs text-slate-500">Cada venta conserva la comisión configurada cuando fue registrada.</p></div>
                    <div class="mt-5 space-y-4">
                        @forelse ($sellers as $seller)
                            @php($stat = $stats->get($seller->user_id))
                            @php($menuOptions = app(\App\Services\SellerMenuService::class)->assignableOptions())
                            @php($enabledMenus = app(\App\Services\SellerMenuService::class)->normalize($seller->menu_permissions))
                            <article class="rounded-xl border border-slate-200 p-4 {{ $seller->is_active ? 'bg-white' : 'bg-slate-50 opacity-70' }}">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div><h2 class="font-bold text-slate-900">{{ $seller->user->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $seller->user->email }}</p><p class="mt-3 text-xs font-semibold text-slate-700">{{ number_format((int) ($stat->sales_count ?? 0)) }} ventas · RD$ {{ number_format((float) ($stat->sales_total ?? 0), 2) }} vendido</p></div>
                                    <div class="text-right"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Comisión acumulada</p><p class="mt-1 text-lg font-black text-emerald-700">RD$ {{ number_format((float) ($stat->commission_total ?? 0), 2) }}</p><p class="mt-1 text-[11px] font-bold {{ $seller->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $seller->is_active ? 'Activo' : 'Desactivado' }}</p></div>
                                </div>
                                <form method="POST" action="{{ route('seller.shops.sellers.update', [$shop, $seller]) }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">@csrf @method('PATCH')
                                    <label class="text-xs font-bold text-slate-700">Modalidad<select name="commission_type" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="percentage" @selected($seller->commission_type === 'percentage')>Porcentaje</option><option value="fixed" @selected($seller->commission_type === 'fixed')>Monto fijo</option></select></label>
                                    <label class="text-xs font-bold text-slate-700">Valor<input name="commission_value" type="number" min="0.01" step="0.01" value="{{ $seller->commission_value }}" class="mt-1 block w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                                    <input type="hidden" name="menu_permissions_configured" value="1">
                                    <div class="w-full rounded-xl bg-slate-50 p-3">
                                        <p class="text-xs font-bold text-slate-700">Menús visibles para este vendedor</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($menuOptions as $key => $label)
                                                <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                                    <input type="checkbox" name="menu_permissions[]" value="{{ $key }}" @checked(in_array($key, $enabledMenus, true)) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <button class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">Guardar</button>
                                </form>
                                @if ($seller->is_active)<form method="POST" action="{{ route('seller.shops.sellers.destroy', [$shop, $seller]) }}" class="mt-2">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-700 hover:underline">Desactivar vendedor</button></form>@endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">Aún no hay vendedores asignados a esta tienda.</div>
                        @endforelse
                    </div>
                </section>

                <aside class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                    <h2 class="text-base font-bold text-slate-900">Invitar vendedor</h2><p class="mt-1 text-xs text-slate-500">Si aún no tiene cuenta, recibirá un enlace para confirmar su correo y crear su contraseña.</p>
                    <form method="POST" action="{{ route('seller.shops.sellers.store', $shop) }}" class="mt-5 space-y-4">@csrf
                        <div><label class="text-xs font-bold text-slate-700">Correo del vendedor</label><input name="email" type="email" required value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                        <div><label class="text-xs font-bold text-slate-700">Modalidad</label><select name="commission_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="percentage" @selected(old('commission_type') === 'percentage')>Porcentaje de venta</option><option value="fixed" @selected(old('commission_type') === 'fixed')>Monto fijo por venta</option></select></div>
                        <div><label class="text-xs font-bold text-slate-700">Valor</label><input name="commission_value" type="number" min="0.01" step="0.01" required value="{{ old('commission_value') }}" placeholder="Ej.: 5.00" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-[11px] text-slate-500">Para porcentaje, escribe 5 para 5%. Para monto fijo, escribe el valor en RD$.</p></div>
                        <button class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Enviar invitación</button>
                    </form>
                </aside>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6"><div class="border-b border-slate-100 pb-5"><h2 class="text-lg font-bold text-slate-900">Historial de ventas y ganancias</h2><p class="mt-1 text-xs text-slate-500">Sólo muestra ventas realizadas por vendedores asignados.</p></div><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-xs uppercase tracking-wide text-slate-500"><tr><th class="pb-3 pr-4">Fecha</th><th class="pb-3 pr-4">Vendedor</th><th class="pb-3 pr-4">Factura</th><th class="pb-3 pr-4">Venta</th><th class="pb-3">Ganancia</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse ($sales as $sale)<tr><td class="py-3 pr-4 text-slate-600">{{ $sale->issued_at->format('d/m/Y H:i') }}</td><td class="py-3 pr-4 font-semibold text-slate-900">{{ $sale->salesperson->name }}</td><td class="py-3 pr-4 font-mono text-xs text-slate-600">{{ $sale->invoice_number }}</td><td class="py-3 pr-4 font-semibold">RD$ {{ number_format((float) $sale->total, 2) }}</td><td class="py-3 font-black text-emerald-700">RD$ {{ number_format((float) $sale->commission_amount, 2) }} <span class="text-[10px] font-medium text-slate-500">({{ $sale->commission_type === 'percentage' ? $sale->commission_value.'%' : 'fijo' }})</span></td></tr>@empty<tr><td colspan="5" class="py-10 text-center text-slate-500">Aún no hay ventas de vendedores.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $sales->links() }}</div></section>
        </div>
    </main>
</x-layouts.app>
