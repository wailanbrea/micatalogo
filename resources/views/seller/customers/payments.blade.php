<x-layouts.app :title="'Historial de abonos | '.$shop->name">
    <x-admin.header :breadcrumbs="[['label' => 'Mis tiendas', 'url' => route('seller.dashboard')], ['label' => $shop->name, 'url' => route('seller.shops.customers.index', $shop)], ['label' => 'Historial de abonos']]" />
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="collections" />
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-2xl font-black text-slate-900">Historial de abonos</h1><p class="mt-1 text-sm text-slate-500">Consulta los cobros y corrige registros por error conservando su historial.</p></div><a href="{{ route('seller.shops.customers.index', $shop) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Volver a clientes</a></div>
            @if(session('status'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            <section class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @forelse($payments as $payment)
                    <article class="p-5" x-data="{ reversing: false, submitting: false }">
                        <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="font-bold text-slate-900">{{ $payment->customer?->name }} <span class="ml-2 text-xs font-medium text-slate-500">REC-{{ $payment->id }}</span></p><p class="mt-1 text-xs text-slate-500">{{ $payment->created_at->timezone(app(\App\Services\ShopOperationalSettingsService::class)->forShop($shop)['timezone'])->format('d/m/Y h:i A') }}</p>@if($payment->notes)<p class="mt-2 text-sm text-slate-600">{{ $payment->notes }}</p>@endif</div><div class="text-right"><p class="text-lg font-black tabular-nums text-slate-900">RD$ {{ number_format(abs((float) $payment->amount), 2) }}</p><span class="text-xs font-bold {{ $payment->reversal ? 'text-rose-700' : 'text-emerald-700' }}">{{ $payment->reversal ? 'Anulado' : 'Aplicado' }}</span></div></div>
                        @if($payment->reversal)
                            <div class="mt-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-900"><p class="font-bold">Anulado por {{ $payment->reversal->user?->name }} · {{ $payment->reversal->created_at->timezone(app(\App\Services\ShopOperationalSettingsService::class)->forShop($shop)['timezone'])->format('d/m/Y h:i A') }}</p><p class="mt-1">{{ $payment->reversal->notes }}</p></div>
                        @elseif($canReverse && $payment->customer)
                            <button type="button" @click="reversing = !reversing" :aria-expanded="reversing" class="mt-3 rounded-lg border border-rose-200 px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50">Anular abono</button>
                            <form method="POST" action="{{ route('seller.shops.customers.payments.reverse', [$shop, $payment->customer, 'paymentId' => $payment->id]) }}" x-show="reversing" x-cloak @submit="if(submitting) { $event.preventDefault(); } else { submitting = true; }" class="mt-3 space-y-3 rounded-xl border border-rose-100 bg-rose-50/40 p-4">
                                @csrf<input type="hidden" name="client_transaction_uuid" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <p class="text-sm text-slate-700">Se restituirá este importe a la deuda del cliente y se corregirán las facturas y la conciliación originales.</p>
                                <label class="block text-sm font-bold text-slate-700" for="reason-{{ $payment->id }}">Motivo de anulación</label><textarea id="reason-{{ $payment->id }}" name="reason" required maxlength="255" rows="2" class="w-full rounded-lg border border-slate-300 bg-white p-3 text-sm" placeholder="Explica el error registrado"></textarea>
                                <div class="flex flex-wrap gap-2"><button type="submit" :disabled="submitting" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">Confirmar anulación</button><button type="button" @click="reversing = false" :disabled="submitting" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button></div>
                            </form>
                        @endif
                    </article>
                @empty<div class="p-10 text-center text-sm text-slate-500">Todavía no hay abonos registrados.</div>@endforelse
            </section>
            {{ $payments->links() }}
        </div>
    </main>
</x-layouts.app>
