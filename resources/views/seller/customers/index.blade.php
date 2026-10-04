<x-layouts.app :title="'Clientes | ' . $shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Clientes']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="customers" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Clientes activos</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($summary['active_count']) }}</p></div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-xs"><p class="text-xs font-bold uppercase tracking-wide text-amber-800">Por cobrar</p><p class="mt-2 text-3xl font-black text-amber-800">RD$ {{ number_format($summary['total_balance'], 2) }}</p></div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-xs"><p class="text-xs font-bold uppercase tracking-wide text-blue-800">Líneas de crédito</p><p class="mt-2 text-3xl font-black text-blue-800">RD$ {{ number_format($summary['total_credit_limit'], 2) }}</p></div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                        <div><h1 class="text-xl font-bold text-slate-900">Clientes y cuentas por cobrar</h1><p class="mt-1 text-xs text-slate-500">El saldo sólo cambia al registrar un crédito o un cobro.</p></div>
                        <form method="GET" class="flex gap-2"><input name="q" value="{{ $search }}" type="search" placeholder="Nombre o teléfono" class="w-44 rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-blue-600 focus:outline-none"><button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Buscar</button></form>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($customers as $customer)
                            <article class="rounded-xl border border-slate-200 p-4 {{ (float) $customer->balance > 0 ? 'bg-amber-50/40' : 'bg-white' }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                     <div><h2 class="font-bold text-slate-900">{{ $customer->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $customer->document_type && $customer->document_number ? strtoupper($customer->document_type).' '.$customer->document_number.' · ' : '' }}{{ $customer->phone ?: 'Sin teléfono' }}{{ $customer->email ? ' · '.$customer->email : '' }}</p></div>
                                    <div class="text-right"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Saldo pendiente</p><p class="text-lg font-black {{ (float) $customer->balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">RD$ {{ number_format((float) $customer->balance, 2) }}</p><p class="text-[11px] text-slate-500">Límite RD$ {{ number_format((float) $customer->credit_limit, 2) }}</p></div>
                                </div>
                                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                                    <form method="POST" action="{{ route('seller.shops.customers.charge', [$shop, $customer]) }}" class="flex flex-wrap gap-2">@csrf<input name="amount" required min="0.01" step="0.01" type="number" placeholder="Monto crédito" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs"><input name="notes" required maxlength="255" placeholder="Motivo" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs"><button class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-700">Registrar crédito</button></form>
                                    <form method="POST" action="{{ route('seller.shops.customers.payment', [$shop, $customer]) }}" class="flex flex-wrap gap-2">@csrf<input name="amount" required min="0.01" step="0.01" type="number" placeholder="Monto cobrado" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs"><input name="notes" maxlength="255" placeholder="Nota (opcional)" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs"><button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Registrar cobro</button></form>
                                </div>
                                @if ($customer->accountEntries->isNotEmpty())
                                    <div class="mt-3 text-xs text-slate-500"><span class="font-semibold text-slate-700">Últimos movimientos:</span> @foreach ($customer->accountEntries as $entry)<span class="ml-2 inline-block">{{ $entry->created_at->format('d/m H:i') }} · {{ $entry->type === 'payment' ? 'Cobro' : ($entry->type === 'charge' ? 'Crédito' : 'Ajuste') }} <strong class="{{ (float) $entry->amount < 0 ? 'text-emerald-700' : 'text-amber-700' }}">RD$ {{ number_format((float) $entry->amount, 2) }}</strong></span>@endforeach</div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">Aún no hay clientes. Crea el primer perfil desde el formulario.</div>
                        @endforelse
                    </div>
                    <div class="mt-5">{{ $customers->links() }}</div>
                </section>

                <aside class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                    <h2 class="text-base font-bold text-slate-900">Nuevo cliente</h2><p class="mt-1 text-xs text-slate-500">El límite inicia en RD$ 0 si no autorizas crédito.</p>
                    <form method="POST" action="{{ route('seller.shops.customers.store', $shop) }}" class="mt-5 space-y-4">@csrf
                         <div><label class="text-xs font-bold text-slate-700" for="customer-first-name">Nombre *</label><input id="customer-first-name" name="first_name" required maxlength="80" value="{{ old('first_name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-last-name">Apellido *</label><input id="customer-last-name" name="last_name" required maxlength="80" value="{{ old('last_name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div class="grid grid-cols-2 gap-2"><div><label class="text-xs font-bold text-slate-700" for="customer-document-type">Documento *</label><select id="customer-document-type" name="document_type" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"><option value="cedula" @selected(old('document_type') === 'cedula')>Cédula</option><option value="pasaporte" @selected(old('document_type') === 'pasaporte')>Pasaporte</option></select></div><div><label class="text-xs font-bold text-slate-700" for="customer-document-number">Número *</label><input id="customer-document-number" name="document_number" required maxlength="40" value="{{ old('document_number') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-phone">Teléfono *</label><input id="customer-phone" name="phone" required maxlength="30" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                        <div><label class="text-xs font-bold text-slate-700" for="customer-email">Correo</label><input id="customer-email" name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                        <div><label class="text-xs font-bold text-slate-700" for="customer-limit">Límite de crédito *</label><input id="customer-limit" name="credit_limit" required min="0" step="0.01" type="number" value="{{ old('credit_limit', '0.00') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-address">Dirección *</label><textarea id="customer-address" name="address" required rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none">{{ old('address') }}</textarea></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-whatsapp">WhatsApp</label><input id="customer-whatsapp" name="whatsapp" maxlength="30" value="{{ old('whatsapp') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-reference">Referencia</label><input id="customer-reference" name="reference" maxlength="255" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                        <div><label class="text-xs font-bold text-slate-700" for="customer-notes">Notas</label><textarea id="customer-notes" name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none">{{ old('notes') }}</textarea></div>
                        <button class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Crear cliente</button>
                    </form>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.app>
