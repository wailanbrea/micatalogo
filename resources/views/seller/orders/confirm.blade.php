<x-layouts.app :title="'Confirmar pedido '.$order->order_number.' | '.$shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Confirmar pedido']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            @if (session('status'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-900">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-950 to-blue-950 px-6 py-7 text-white sm:px-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-200">Pedido recibido por WhatsApp</p>
                            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $order->order_number }}</h1>
                            <p class="mt-2 text-sm text-blue-100">Revisa la orden, registra cómo fue cobrada y genera su factura.</p>
                        </div>
                        @if ($order->invoice)
                            <span class="rounded-full bg-emerald-400/20 px-3 py-1.5 text-xs font-black text-emerald-100">Factura {{ $order->invoice->invoice_number }}</span>
                        @else
                            <span class="rounded-full bg-amber-400/20 px-3 py-1.5 text-xs font-black text-amber-100">Pendiente de confirmar</span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-6 p-5 sm:p-8 lg:grid-cols-[1.15fr_.85fr]">
                    <div class="space-y-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">Cliente</p>
                                <p class="mt-1 font-bold text-slate-900">{{ $order->customer_name ?: 'No indicado' }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">Entrega</p>
                                <p class="mt-1 font-bold text-slate-900">{{ $order->delivery_type === 'delivery' ? 'Envío a domicilio' : ($order->delivery_type === 'pickup' ? 'Retiro en tienda' : 'Por coordinar') }}</p>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-base font-black text-slate-950">Productos del pedido</h2>
                            <div class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                                @foreach ($order->items as $item)
                                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $item->product_name }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $item->quantity }} × RD$ {{ number_format((float) $item->unit_price, 2) }}</p>
                                        </div>
                                        <p class="shrink-0 text-sm font-black tabular-nums text-slate-900">RD$ {{ number_format((float) $item->line_total, 2) }}</p>
                                    </div>
                                @endforeach
                                <div class="flex items-center justify-between bg-slate-50 px-4 py-4">
                                    <span class="text-sm font-black text-slate-700">Total del pedido</span>
                                    <span class="text-xl font-black tabular-nums text-slate-950">RD$ {{ number_format((float) $order->total, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        @if ($order->notes)
                            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-950"><span class="font-black">Notas:</span> {{ $order->notes }}</div>
                        @endif
                    </div>

                    @if ($order->invoice)
                        <aside class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Venta confirmada</p>
                            <h2 class="mt-2 text-xl font-black text-emerald-950">Factura generada</h2>
                            <p class="mt-2 text-sm leading-6 text-emerald-900">La existencia ya fue rebajada y el pago quedó registrado.</p>
                            <dl class="mt-5 space-y-2 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-emerald-700">Número</dt><dd class="font-black text-emerald-950">{{ $order->invoice->invoice_number }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-emerald-700">Estado</dt><dd class="font-black capitalize text-emerald-950">{{ $order->invoice->status }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-emerald-700">Total</dt><dd class="font-black text-emerald-950">RD$ {{ number_format((float) $order->invoice->total, 2) }}</dd></div>
                            </dl>
                            @if ($invoiceUrl)
                                <a href="{{ $invoiceUrl }}" target="_blank" rel="noopener" class="mt-6 inline-flex w-full items-center justify-center rounded-xl border border-emerald-300 bg-white px-4 py-3 text-sm font-black text-emerald-800 hover:bg-emerald-100">Ver / descargar factura PDF</a>
                            @endif
                            <a href="{{ route('seller.shops.business', $shop) }}#orders" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white hover:bg-emerald-800">Volver a pedidos</a>
                        </aside>
                    @else
                        <aside x-data="orderConfirmation({{ (float) $order->total }})" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-lg font-black text-slate-950">Registrar cobro</h2>
                            <p class="mt-1 text-sm text-slate-500">Elige el método real. Para crédito o pago mixto, selecciona el cliente.</p>
                            <form method="POST" action="{{ route('seller.shops.orders.confirm', [$shop, $order]) }}" class="mt-5 space-y-4">
                                @csrf
                                <div>
                                    <label class="text-xs font-black text-slate-700" for="payment_kind">Forma de cobro</label>
                                    <select id="payment_kind" name="payment_kind" x-model="kind" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                        <option value="paid">Contado completo</option>
                                        <option value="mixed">Pago parcial + crédito</option>
                                        <option value="credit">A crédito</option>
                                    </select>
                                </div>

                                <div x-show="kind !== 'credit'" x-cloak>
                                    <label class="text-xs font-black text-slate-700" for="payment_method">Método de pago</label>
                                    <select id="payment_method" name="payment_method" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                        @foreach ($paymentMethods as $method => $config)
                                            @continue($method === 'credit')
                                            <option value="{{ $method }}">{{ $config['label'] ?? $method }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div x-show="kind === 'mixed'" x-cloak>
                                    <label class="text-xs font-black text-slate-700" for="credit_amount">Monto que queda a crédito</label>
                                    <input id="credit_amount" name="credit_amount" x-model="creditAmount" type="number" min="0.01" step="0.01" :max="total - 0.01" :required="kind === 'mixed'" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                </div>

                                <div x-show="kind !== 'paid'" x-cloak>
                                    <label class="text-xs font-black text-slate-700" for="customer_id">Cliente responsable</label>
                                    <select id="customer_id" name="customer_id" :required="kind !== 'paid'" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                        <option value="">Selecciona un cliente</option>
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->name }} · debe RD$ {{ number_format((float) $customer->balance, 2) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($customers->isEmpty())
                                        <p class="mt-1 text-xs font-semibold text-amber-700">No hay clientes activos. Créalo primero en Clientes y cobros.</p>
                                    @endif
                                </div>

                                <div>
                                    <label class="text-xs font-black text-slate-700" for="reference">Referencia (opcional)</label>
                                    <input id="reference" name="reference" type="text" maxlength="120" placeholder="Ej. transferencia 4821" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                                </div>

                                <div class="rounded-2xl bg-slate-50 p-4 text-sm">
                                    <div class="flex justify-between gap-3"><span class="text-slate-500">Total</span><span class="font-black text-slate-950">RD$ {{ number_format((float) $order->total, 2) }}</span></div>
                                    <div x-show="kind === 'mixed'" x-cloak class="mt-2 flex justify-between gap-3 text-amber-700"><span>Queda a crédito</span><span class="font-black" x-text="money(creditAmount)"></span></div>
                                </div>

                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-3 text-sm font-black text-white shadow-lg shadow-blue-100 transition hover:bg-blue-800">Confirmar venta y generar factura</button>
                            </form>
                        </aside>
                    @endif
                </div>
            </section>
        </div>
    </main>

    <script>
        function orderConfirmation(total) {
            return {
                total,
                kind: 'paid',
                creditAmount: total,
                money(value) { return 'RD$ ' + Number(value || 0).toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
</x-layouts.app>
