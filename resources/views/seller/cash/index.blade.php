<x-layouts.app :title="'Control de Caja | ' . $shop->name">
    <x-admin.header
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Control de caja']
        ]"
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <x-seller.shop-header :shop="$shop" activeTab="cash" />

            <!-- Flash Status & Errors -->
            @if (session('status'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900 shadow-xs flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-900 shadow-xs">
                    <div class="flex items-center gap-2 mb-2 font-bold text-rose-950">
                        <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Atención:</span>
                    </div>
                    <ul class="list-disc pl-5 space-y-1 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Page Title Bar -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800 font-black text-lg shadow-2xs">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2Zm7-5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                            </svg>
                        </span>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Control avanzado de caja</h1>
                            <p class="mt-0.5 text-xs text-slate-500">Sesiones opcionales para negocios que necesitan controlar turnos, entradas/salidas y arqueos.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('seller.shops.business', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>Resumen del negocio ↗</span>
                    </a>
                    <a href="{{ route('seller.shops.expenses.index', $shop) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition">
                        <span>Gastos operativos</span>
                    </a>
                </div>
            </div>

            <!-- CURRENT SESSION CONTAINER -->
            @if (! $currentSession)
                <!-- No active session: Open box card -->
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center sm:p-12 shadow-xs">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 mb-4">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight">No hay sesión avanzada activa</h2>
                    <p class="mx-auto mt-2 max-w-md text-xs text-slate-500">
                        Puedes seguir vendiendo y registrando gastos normalmente. Abre una sesión solo si necesitas controlar un turno o un fondo inicial.
                    </p>

                    <form method="POST" action="{{ route('seller.shops.cash.open', $shop) }}" class="mx-auto mt-6 max-w-md space-y-4 text-left">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Fondo inicial en efectivo (RD$)</label>
                            <div class="relative rounded-2xl shadow-2xs">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 font-bold text-slate-400 text-sm">RD$</span>
                                <input
                                    type="number"
                                    name="opening_amount"
                                    step="0.01"
                                    min="0"
                                    value="0.00"
                                    required
                                    class="block w-full rounded-2xl border border-slate-300 bg-slate-50/50 pl-14 pr-4 py-3 text-base font-black text-slate-900 tabular-nums focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/20"
                                    placeholder="0.00"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Notas / Comentario de apertura (opcional)</label>
                            <input
                                type="text"
                                name="notes"
                                class="block w-full rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/20"
                                placeholder="Ej: Turno mañana, sencillo en billetes de 50 y 100"
                            >
                        </div>

                        <button
                            type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3.5 text-sm font-black text-white shadow-sm hover:bg-emerald-700 transition cursor-pointer"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                            </svg>
                            <span>Abrir sesión avanzada</span>
                        </button>
                    </form>
                </div>
            @else
                <!-- Active Session Live Dashboard -->
                <div class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-5">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3.5 w-3.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-black text-slate-900">Sesión de caja activa</h2>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                                        En curso
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Abierta por <strong class="text-slate-700">{{ $currentSession->user->name }}</strong> el {{ $currentSession->opened_at->format('d/m/Y h:i A') }}
                                </p>
                            </div>
                        </div>

                        <!-- Expected live balance -->
                        @php
                            $expectedNow = $currentSession->calculateExpectedBalance() / 100.0;
                        @endphp
                        <div class="rounded-2xl bg-emerald-50/70 border border-emerald-200/70 px-5 py-3 text-right">
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Efectivo esperado en caja</span>
                            <p class="text-2xl font-black text-emerald-700 tabular-nums whitespace-nowrap">
                                RD$ {{ number_format($expectedNow, 2) }}
                            </p>
                        </div>
                    </div>

                    <!-- Flow breakdown badges -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 text-xs">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <span class="text-[10px] uppercase font-bold text-slate-500">Fondo inicial</span>
                            <p class="text-sm font-black text-slate-900 tabular-nums whitespace-nowrap mt-1">
                                RD$ {{ number_format($currentSession->opening_amount_cents / 100.0, 2) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <span class="text-[10px] uppercase font-bold text-emerald-600">Ventas efectivo</span>
                            <p class="text-sm font-black text-emerald-700 tabular-nums whitespace-nowrap mt-1">
                                +RD$ {{ number_format(($sessionSummary['sales_cash'] ?? 0), 2) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <span class="text-[10px] uppercase font-bold text-indigo-600">Cobros a crédito</span>
                            <p class="text-sm font-black text-indigo-700 tabular-nums whitespace-nowrap mt-1">
                                +RD$ {{ number_format(($sessionSummary['collections_cash'] ?? 0), 2) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <span class="text-[10px] uppercase font-bold text-blue-600">Entradas manuales</span>
                            <p class="text-sm font-black text-blue-700 tabular-nums whitespace-nowrap mt-1">
                                +RD$ {{ number_format(($sessionSummary['cash_in'] ?? 0), 2) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <span class="text-[10px] uppercase font-bold text-rose-600">Gastos en efectivo</span>
                            <p class="text-sm font-black text-rose-700 tabular-nums whitespace-nowrap mt-1">
                                -RD$ {{ number_format(abs($sessionSummary['expenses_cash'] ?? 0), 2) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <span class="text-[10px] uppercase font-bold text-amber-600">Salidas / Retiros</span>
                            <p class="text-sm font-black text-amber-700 tabular-nums whitespace-nowrap mt-1">
                                -RD$ {{ number_format(abs(($sessionSummary['cash_out'] ?? 0) + ($sessionSummary['owner_withdrawals'] ?? 0)), 2) }}
                            </p>
                        </div>
                    </div>

                    <!-- ACTION SECTION: Register Movement & Close Session -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 pt-2">
                        <!-- Movement Form -->
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5 space-y-4">
                            <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                                <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <span>Registrar movimiento de efectivo</span>
                            </h3>

                            <form method="POST" action="{{ route('seller.shops.cash.movement', [$shop, $currentSession]) }}" class="space-y-3 text-xs">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Tipo de movimiento</label>
                                        <select name="type" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-blue-600">
                                            @foreach ($allowedTypes as $typeKey => $typeLabel)
                                                <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Monto (RD$)</label>
                                        <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-900 tabular-nums focus:border-blue-600">
                                    </div>
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Motivo / Justificación (obligatorio)</label>
                                    <input type="text" name="notes" required placeholder="Ej: Compra de hielo, cambio de billete grande, etc." class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-blue-600">
                                </div>
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition cursor-pointer">
                                    <span>Guardar movimiento</span>
                                </button>
                            </form>
                        </div>

                        <!-- Close Session & Reconciliation Form -->
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-5 space-y-4">
                            <h3 class="text-sm font-black text-amber-950 flex items-center gap-2">
                                <svg class="h-4 w-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span>Cierre y Arqueo de Caja</span>
                            </h3>
                            <p class="text-xs text-amber-800">
                                Cuenta físicamente el dinero en la gaveta e introduce el total. El sistema calculará automáticamente si la caja está cuadrada, o si hay sobrante o faltante.
                            </p>

                            <form method="POST" action="{{ route('seller.shops.cash.close', [$shop, $currentSession]) }}" class="space-y-3 text-xs">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Monto contado en gaveta (RD$)</label>
                                        <input type="number" name="counted_amount" step="0.01" min="0" placeholder="Opcional" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-900 tabular-nums focus:border-amber-600">
                                        <p class="mt-1 text-[10px] text-slate-500">Déjalo vacío para cerrar sin arqueo.</p>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Notas de cierre (opcional)</label>
                                        <input type="text" name="notes" placeholder="Observaciones del turno" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-amber-600">
                                    </div>
                                </div>
                                <button type="submit" onclick="return confirm('¿Confirmas el cierre del turno de caja?');" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-700 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-amber-800 transition cursor-pointer">
                                    <span>Cerrar turno y arquear</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Live Movements Table -->
                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-sm font-black text-slate-900 mb-3">Movimientos de este turno ({{ $movements->count() }})</h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Hora</th>
                                        <th class="px-4 py-2.5">Tipo</th>
                                        <th class="px-4 py-2.5">Motivo / Referencia</th>
                                        <th class="px-4 py-2.5 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($movements as $m)
                                        @php
                                            $isPos = $m->amount_cents > 0;
                                        @endphp
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="px-4 py-2.5 text-slate-500 font-mono">
                                                {{ $m->occurred_at->format('h:i A') }}
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold capitalize {{ $isPos ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                                    {{ str_replace('_', ' ', $m->type) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-slate-700">
                                                {{ $m->notes ?: '—' }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-black tabular-nums whitespace-nowrap {{ $isPos ? 'text-emerald-700' : 'text-rose-700' }}">
                                                {{ $isPos ? '+' : '' }}RD$ {{ number_format($m->amount_cents / 100.0, 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-4 text-center text-slate-400">Sin movimientos registrados en este turno todavía.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- PAST SESSIONS HISTORIC -->
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-black text-slate-900 mb-1">Historial de sesiones avanzadas</h2>
                <p class="text-xs text-slate-500 mb-4">Registro auditable de sesiones opcionales con comparativa de esperado vs contado.</p>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Apertura</th>
                                <th class="px-4 py-3">Cierre</th>
                                <th class="px-4 py-3">Cajero</th>
                                <th class="px-4 py-3 text-right">Fondo inicial</th>
                                <th class="px-4 py-3 text-right">Esperado</th>
                                <th class="px-4 py-3 text-right">Contado</th>
                                <th class="px-4 py-3 text-center">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($pastSessions as $s)
                                @php
                                    $diff = $s->difference_cents / 100.0;
                                @endphp
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 text-slate-600 font-mono">
                                        {{ $s->opened_at->format('d/m/Y h:i A') }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 font-mono">
                                        {{ $s->closed_at ? $s->closed_at->format('d/m/Y h:i A') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">
                                        {{ $s->user?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-slate-700">
                                        RD$ {{ number_format($s->opening_amount_cents / 100.0, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-semibold text-slate-800">
                                        RD$ {{ number_format($s->expected_closing_amount_cents / 100.0, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap font-black text-slate-900">
                                        RD$ {{ number_format($s->counted_closing_amount_cents / 100.0, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @if ($diff == 0.0)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black text-emerald-700 border border-emerald-200">
                                                Cuadrada (RD$ 0.00)
                                            </span>
                                        @elseif ($diff > 0)
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-black text-blue-700 border border-blue-200">
                                                +RD$ {{ number_format($diff, 2) }} (Sobrante)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-black text-rose-700 border border-rose-200">
                                                -RD$ {{ number_format(abs($diff), 2) }} (Faltante)
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                                        Sin sesiones cerradas anteriormente.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($pastSessions->hasPages())
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        {{ $pastSessions->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
