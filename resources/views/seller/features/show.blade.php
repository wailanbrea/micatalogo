<x-layouts.app :title="$feature['title'].' | '.$shop->name">
    <x-admin.header />

    <main class="min-h-screen bg-[#f7f7f6] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-[1600px] space-y-5">
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
                            {{ $module['kind'] === 'prepared' ? $feature['status'] : 'Operativo' }}
                        </span>
                    </div>
                </div>

                @if ($module['kind'] !== 'prepared')
                    <div class="space-y-6 p-4 sm:p-6 lg:p-8">
                        <div class="grid gap-4 sm:grid-cols-3">
                            @foreach ($module['kpis'] as $kpi)
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $kpi['label'] }}</p>
                                    <p class="mt-2 text-2xl font-black tabular-nums {{ match ($kpi['tone']) { 'emerald' => 'text-emerald-700', 'amber' => 'text-amber-700', 'rose' => 'text-rose-700', 'blue' => 'text-blue-700', default => 'text-slate-900' } }}">{{ $kpi['value'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if (session('status'))
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif

                        @if ($featureKey === 'quotes')
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5">
                                <div class="mb-4"><h2 class="text-base font-black text-slate-900">Nueva cotización</h2><p class="mt-1 text-xs text-slate-500">Guárdala como borrador y conviértela en venta cuando el cliente confirme.</p></div>
                                <form method="POST" action="{{ route('seller.shops.quotes.store', $shop) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @csrf
                                    <input name="customer_name" value="{{ old('customer_name') }}" placeholder="Nombre del cliente" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <input name="customer_phone" value="{{ old('customer_phone') }}" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <select name="items[0][product_id]" required class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Producto</option>@foreach (($module['quoteProducts'] ?? []) as $product)<option value="{{ $product['id'] }}">{{ $product['name'] }} · RD$ {{ $product['price'] }}</option>@endforeach</select>
                                    <div class="flex gap-2"><input name="items[0][quantity]" value="1" min="1" required type="number" placeholder="Cantidad" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="items[0][unit_price]" placeholder="Precio" type="number" min="0" step="0.01" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></div>
                                    <textarea name="notes" placeholder="Notas (opcional)" class="sm:col-span-2 lg:col-span-3 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></textarea>
                                    <button class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Guardar cotización</button>
                                </form>
                            </div>
                        @endif

                        @if (in_array($featureKey, ['containers', 'loads', 'purchase_invoices'], true))
                            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 sm:p-5">
                                <div class="mb-4"><h2 class="text-base font-black text-slate-900">Registrar recepción de compra</h2><p class="mt-1 text-xs text-slate-500">La recepción crea un lote separado y conserva el costo exacto de esta compra.</p></div>
                                <form method="POST" action="{{ route('seller.shops.purchases.store', $shop) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $featureKey === 'containers' ? 'container' : ($featureKey === 'loads' ? 'load' : 'purchase_invoice') }}">
                                    <input required name="document_number" placeholder="No. documento" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <select name="supplier_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Suplidor (opcional)</option>@foreach (($module['suppliers'] ?? []) as $supplier)<option value="{{ $supplier['id'] }}">{{ $supplier['name'] }}</option>@endforeach</select>
                                    <select required name="product_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Producto</option>@foreach (($module['purchaseProducts'] ?? []) as $product)<option value="{{ $product['id'] }}">{{ $product['name'] }}</option>@endforeach</select>
                                    <input required name="quantity" type="number" min="1" placeholder="Cantidad" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <input required name="unit_cost" type="number" min="0" step="0.01" placeholder="Costo unitario" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                    <button class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-black text-white hover:bg-amber-700">Recibir inventario</button>
                                </form>
                            </div>
                        @elseif ($featureKey === 'suppliers')
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5"><div class="mb-4"><h2 class="text-base font-black text-slate-900">Nuevo suplidor</h2><p class="mt-1 text-xs text-slate-500">Guarda sus datos para asociarlos a futuras recepciones.</p></div><form method="POST" action="{{ route('seller.shops.suppliers.store', $shop) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf<input required name="name" placeholder="Nombre del suplidor" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="phone" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="email" type="email" placeholder="Correo" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><button class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Guardar suplidor</button></form></div>
                        @endif

                        @if ($featureKey === 'partners')
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 sm:p-5"><h2 class="mb-4 text-base font-black text-slate-900">Nuevo socio</h2><form method="POST" action="{{ route('seller.shops.partners.store', $shop) }}" class="grid gap-3 sm:grid-cols-2">@csrf<input required name="name" placeholder="Nombre" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="ownership_percent" type="number" min="0" max="100" step="0.01" placeholder="Participación %" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="email" type="email" placeholder="Correo" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="phone" placeholder="Teléfono" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><button class="sm:col-span-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Guardar socio</button></form></div>
                                <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 sm:p-5"><h2 class="mb-4 text-base font-black text-slate-900">Aporte o retiro</h2><form method="POST" action="{{ route('seller.shops.partners.transactions.store', [$shop, 'partner' => '__partner__']) }}" onsubmit="this.action=this.action.replace('__partner__',this.partner_id.value)" class="grid gap-3 sm:grid-cols-2">@csrf<select required name="partner_id" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Socio</option>@foreach (($module['partners'] ?? []) as $partner)<option value="{{ $partner['id'] }}">{{ $partner['name'] }}</option>@endforeach</select><select required name="type" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="contribution">Aporte</option><option value="withdrawal">Retiro</option><option value="distribution">Distribución</option></select><input required name="amount" type="number" min="0.01" step="0.01" placeholder="Monto" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><input name="notes" placeholder="Nota" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><button class="sm:col-span-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-black text-white hover:bg-amber-700">Registrar en caja</button></form></div>
                            </div>
                        @endif

                        @if ($featureKey === 'day_close')
                            <div class="rounded-2xl border {{ $module['session'] ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50' }} p-4 sm:p-5">
                                @if ($module['session'])
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                        <div>
                                            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700">Sesión de caja activa</p>
                                            <p class="mt-1 text-sm text-slate-600">Efectivo esperado: <strong class="text-lg text-emerald-800">RD$ {{ $module['session']['expected'] }}</strong></p>
                                        </div>
                                        <form method="POST" action="{{ route('seller.shops.cash.close', [$shop, $module['session']['id']]) }}" class="grid w-full gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-end">
                                            @csrf
                                            <div><label class="block text-[11px] font-bold text-slate-600" for="day-close-counted">Efectivo contado</label><input id="day-close-counted" name="counted_amount" required min="0" step="0.01" type="number" placeholder="0.00" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-black tabular-nums sm:w-40"></div>
                                            <button class="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 sm:w-auto">Cerrar caja</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-black text-slate-800">No hay una sesión de caja abierta</p><p class="mt-1 text-xs text-slate-500">Abre la caja para que los cobros en efectivo queden conciliados.</p></div><a wire:navigate.hover href="{{ route('seller.shops.cash.index', $shop) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700">Abrir caja</a></div>
                                @endif
                            </div>
                        @endif

                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div>
                                    <h2 class="text-base font-black text-slate-900">Actividad reciente</h2>
                                    <p class="mt-1 text-xs text-slate-500">Datos tomados de los módulos contables y de inventario existentes.</p>
                                </div>
                                @if (! empty($module['actions']))
                                    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                                        @foreach ($module['actions'] as $action)
                                            <a wire:navigate.hover href="{{ $action['url'] }}" class="flex-1 rounded-xl px-3.5 py-2 text-center text-xs font-black sm:flex-none {{ ($action['tone'] ?? 'secondary') === 'primary' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-200 bg-white text-slate-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700' }}">{{ $action['label'] }}</a>
                                        @endforeach
                                    </div>
                                @elseif ($featureKey === 'sales')
                                    <a wire:navigate.hover href="{{ route('seller.shops.pos', $shop) }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Nueva venta</a>
                                @elseif (in_array($featureKey, ['orders', 'encargos', 'shipments'], true))
                                    <a wire:navigate.hover href="{{ route('seller.shops.pos', $shop) }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Ir a Terminal</a>
                                @elseif ($featureKey === 'credit')
                                    <a wire:navigate.hover href="{{ route('seller.shops.customers.index', $shop) }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-black text-white hover:bg-blue-700">Gestionar clientes</a>
                                @endif
                            </div>

                            @if (count($module['rows']))
                                <div class="divide-y divide-slate-100">
                                    @foreach ($module['rows'] as $row)
                                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-black text-slate-900">{{ $row['primary'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $row['secondary'] }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                                                <span class="text-sm font-black tabular-nums text-slate-900">{{ $row['value'] }}</span>
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $row['status'] }}</span>
                                                @if (($row['can_confirm'] ?? false) && in_array($featureKey, ['orders', 'encargos', 'shipments'], true))
                                                    <form method="POST" action="{{ route('seller.shops.orders.confirm', [$shop, $row['id']]) }}">
                                                        @csrf
                                                        <button class="rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-black text-white hover:bg-emerald-700">Confirmar venta</button>
                                                    </form>
                                                @endif
                                                @if (($row['can_convert'] ?? false) && $featureKey === 'quotes')
                                                    <form method="POST" action="{{ route('seller.shops.quotes.convert', [$shop, 'quote' => $row['id']]) }}">
                                                        @csrf
                                                        <button class="rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-black text-white hover:bg-emerald-700">Convertir en venta</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="px-5 py-12 text-center text-sm text-slate-500">No hay registros para mostrar todavía.</div>
                            @endif
                        </div>

                        @if ($module['note'])
                            <div class="rounded-2xl border border-blue-100 bg-blue-50/60 px-5 py-4 text-sm leading-6 text-blue-900">{{ $module['note'] }}</div>
                        @endif
                    </div>
                @else
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
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
