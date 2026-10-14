<x-layouts.app :title="'Clientes | ' . $shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Clientes']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8" x-data="{ creatingCustomer: false }" x-init="{{ $errors->any() && old('first_name') !== null ? '$nextTick(() => $refs.customerDialog.showModal())' : '' }}">
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

            <div>
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                        <div><h1 class="text-xl font-bold text-slate-900">Clientes y cuentas por cobrar</h1><p class="mt-1 text-xs text-slate-500">El saldo sólo cambia al registrar un crédito o un cobro.</p></div>
                        <div class="customer-list-tools">
                            <form method="GET" class="flex gap-2"><input name="q" value="{{ $search }}" type="search" placeholder="Nombre o teléfono" class="w-44 rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-blue-600 focus:outline-none"><button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Buscar</button></form>
                            <button type="button" class="customer-new-button" @click="$refs.customerDialog.showModal(); $nextTick(() => $refs.customerFirstName.focus())">+ Nuevo cliente</button>
                        </div>
                    </div>

                    <div class="customer-table-wrapper mt-5">
                        <table class="customer-table">
                            <caption class="sr-only">Clientes y sus cuentas. Selecciona un cliente para consultar sus movimientos.</caption>
                            <thead><tr><th scope="col">Cliente</th><th scope="col" class="customer-extra-column">Teléfono</th><th scope="col" class="customer-extra-column">Documento</th><th scope="col" class="customer-money-column">Saldo pendiente</th><th scope="col" class="customer-extra-column customer-money-column">Límite de crédito</th><th scope="col">Estado</th></tr></thead>
                        @forelse ($customers as $customer)
                            <tbody x-data="{ open: false }">
                                <tr class="customer-table-row" @click="open = !open" :class="open ? 'is-open' : ''">
                                    <th scope="row"><button type="button" class="customer-row-toggle" @click.stop="open = !open" :aria-expanded="open" aria-controls="customer-detail-{{ $customer->public_id }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" :style="open ? 'transform:rotate(90deg)' : ''"><path d="m9 5 7 7-7 7"/></svg><span>{{ $customer->name }}</span></button></th>
                                    <td class="customer-extra-column">{{ $customer->phone ?: '—' }}</td>
                                    <td class="customer-extra-column">{{ $customer->document_number ?: '—' }}</td>
                                    <td class="customer-money-column"><strong class="{{ (float) $customer->balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">RD$ {{ number_format((float) $customer->balance, 2) }}</strong></td>
                                    <td class="customer-extra-column customer-money-column">RD$ {{ number_format((float) $customer->credit_limit, 2) }}</td>
                                    <td><span class="customer-table-status {{ (float) $customer->balance > 0 ? 'has-debt' : '' }}">{{ (float) $customer->balance > 0 ? 'Pendiente' : 'Al día' }}</span></td>
                                </tr>
                                <tr x-show="open" x-cloak id="customer-detail-{{ $customer->public_id }}"><td colspan="6" class="customer-detail-cell">
                                    <div class="customer-account-content">
                                    <h3 class="text-sm font-bold text-slate-800">Gestionar cuenta de {{ $customer->name }}</h3>
                                    <dl class="customer-detail-data">
                                        @foreach (['Teléfono' => $customer->phone, 'Documento' => trim(($customer->document_type ?? '').' '.($customer->document_number ?? '')), 'Correo' => $customer->email, 'Dirección' => $customer->address, 'WhatsApp' => $customer->whatsapp, 'Referencia' => $customer->reference, 'Notas' => $customer->notes, 'Límite de crédito' => 'RD$ '.number_format((float) $customer->credit_limit, 2)] as $label => $value)
                                            <div><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                                        @endforeach
                                    </dl>
                                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                                    <form method="POST" action="{{ route('seller.shops.customers.charge', [$shop, $customer]) }}" class="flex flex-wrap gap-2">@csrf<input name="amount" required min="0.01" step="0.01" type="number" placeholder="Monto crédito" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs"><input name="notes" required maxlength="255" placeholder="Motivo" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs"><button class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-700">Registrar crédito</button></form>
                                    <form method="POST" action="{{ route('seller.shops.customers.payment', [$shop, $customer]) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input name="amount" required min="0.01" step="0.01" type="number" placeholder="Monto cobrado" class="min-w-0 w-28 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold tabular-nums">
                                        <select name="payment_method" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs font-medium text-slate-700">
                                            <option value="cash">Efectivo</option>
                                            <option value="card">Tarjeta</option>
                                            <option value="bank_transfer">Transferencia</option>
                                            <option value="other">Otro</option>
                                        </select>
                                        <input name="reference" maxlength="120" placeholder="Ref." class="min-w-0 w-20 rounded-lg border border-slate-300 px-2 py-2 text-xs">
                                        <input name="notes" maxlength="255" placeholder="Nota" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2 py-2 text-xs">
                                        <button class="whitespace-nowrap rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Registrar cobro</button>
                                    </form>
                                </div>
                                @if ($customer->accountEntries->isNotEmpty())
                                    <section class="customer-movements" aria-label="Últimos movimientos de {{ $customer->name }}">
                                        <h3 class="text-sm font-bold text-slate-800">Últimos movimientos</h3>
                                        @foreach ($customer->accountEntries->groupBy(fn ($entry) => $entry->created_at->copy()->timezone('America/Santo_Domingo')->format('Y-m-d')) as $date => $entries)
                                            <div class="customer-movement-day">
                                                <h4 class="customer-movement-date"><time datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->locale('es')->translatedFormat('l j \d\e F \d\e Y') }}</time></h4>
                                                <ol class="customer-movement-list">
                                                    @foreach ($entries as $entry)
                                                        @php
                                                            $isPayment = $entry->type === 'payment';
                                                            $entryLabel = $isPayment ? 'Abono recibido' : ($entry->type === 'charge' ? 'Crédito registrado' : 'Ajuste de cuenta');
                                                            $localTime = $entry->created_at->copy()->timezone('America/Santo_Domingo');
                                                        @endphp
                                                        <li class="customer-movement-row">
                                                            <span class="customer-movement-icon {{ $isPayment ? 'is-payment' : 'is-charge' }}" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $isPayment ? 'M17 7 7 17M7 7v10h10' : 'M7 17 17 7M7 7h10v10' }}"/></svg></span>
                                                            <div class="customer-movement-description">
                                                                <p class="font-bold text-slate-800">{{ $entryLabel }}</p>
                                                                <time class="text-xs text-slate-500" datetime="{{ $localTime->toIso8601String() }}">{{ $localTime->format('g:i a') }}</time>
                                                                @if ($entry->notes)<p class="customer-movement-note">{{ $entry->notes }}</p>@endif
                                                            </div>
                                                            <div class="customer-movement-amount">
                                                                <strong class="{{ (float) $entry->amount < 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ (float) $entry->amount < 0 ? '−' : '+' }} RD$ {{ number_format(abs((float) $entry->amount), 2) }}</strong>
                                                                <span>Saldo: RD$ {{ number_format((float) $entry->balance_after, 2) }}</span>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endforeach
                                    </section>
                                @else
                                    <p class="customer-account-empty">Este cliente todavía no tiene movimientos registrados.</p>
                                @endif
                                    </div>
                                </td></tr>
                            </tbody>
                        @empty
                            <tbody><tr><td colspan="6" class="p-6 text-center text-slate-500">Aún no hay clientes. Crea el primer perfil desde el formulario.</td></tr></tbody>
                        @endforelse
                        </table>
                    </div>
                    <div class="mt-5">{{ $customers->links() }}</div>
                </section>

                <dialog x-ref="customerDialog" class="customer-create-dialog" aria-labelledby="customer-create-title" @cancel="if (creatingCustomer) $event.preventDefault()">
                    <header class="customer-dialog-header"><div><h2 id="customer-create-title" class="text-lg font-bold text-slate-900">Nuevo cliente</h2><p class="mt-1 text-xs text-slate-500">El límite inicia en RD$ 0 si no autorizas crédito.</p></div><button type="button" @click="$refs.customerDialog.close()" :disabled="creatingCustomer" aria-label="Cerrar nuevo cliente" class="customer-dialog-close">×</button></header>
                    @if ($errors->any() && old('first_name') !== null)<div class="p-4 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif
                    <form method="POST" action="{{ route('seller.shops.customers.store', $shop) }}" class="customer-create-form" @submit="if (creatingCustomer) $event.preventDefault(); else creatingCustomer = true">@csrf
                         <div><label class="text-xs font-bold text-slate-700" for="customer-first-name">Nombre *</label><input x-ref="customerFirstName" id="customer-first-name" name="first_name" required maxlength="80" value="{{ old('first_name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-last-name">Apellido *</label><input id="customer-last-name" name="last_name" required maxlength="80" value="{{ old('last_name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div class="grid grid-cols-2 gap-2"><div><label class="text-xs font-bold text-slate-700" for="customer-document-type">Documento *</label><select id="customer-document-type" name="document_type" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"><option value="cedula" @selected(old('document_type') === 'cedula')>Cédula</option><option value="pasaporte" @selected(old('document_type') === 'pasaporte')>Pasaporte</option></select></div><div><label class="text-xs font-bold text-slate-700" for="customer-document-number">Número *</label><input id="customer-document-number" name="document_number" required maxlength="40" value="{{ old('document_number') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-phone">Teléfono *</label><input id="customer-phone" name="phone" required maxlength="30" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                        <div><label class="text-xs font-bold text-slate-700" for="customer-email">Correo</label><input id="customer-email" name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                        <div><label class="text-xs font-bold text-slate-700" for="customer-limit">Límite de crédito *</label><input id="customer-limit" name="credit_limit" required min="0" step="0.01" type="number" value="{{ old('credit_limit', '0.00') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-address">Dirección *</label><textarea id="customer-address" name="address" required rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none">{{ old('address') }}</textarea></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-whatsapp">WhatsApp</label><input id="customer-whatsapp" name="whatsapp" maxlength="30" value="{{ old('whatsapp') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                         <div><label class="text-xs font-bold text-slate-700" for="customer-reference">Referencia</label><input id="customer-reference" name="reference" maxlength="255" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none"></div>
                        <div><label class="text-xs font-bold text-slate-700" for="customer-notes">Notas</label><textarea id="customer-notes" name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none">{{ old('notes') }}</textarea></div>
                        <footer class="customer-dialog-footer"><button type="button" @click="$refs.customerDialog.close()" :disabled="creatingCustomer" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Cancelar</button><button type="submit" :disabled="creatingCustomer" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700" x-text="creatingCustomer ? 'Guardando…' : 'Crear cliente'">Crear cliente</button></footer>
                    </form>
                </dialog>
            </div>
        </div>
    </main>
</x-layouts.app>
