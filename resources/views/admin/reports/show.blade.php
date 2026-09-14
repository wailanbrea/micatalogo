<x-layouts.app title="Revisión de Reporte | MiCatalogo Admin">
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <!-- Unified Admin Header with Breadcrumbs & Back Navigation -->
        <x-admin.header 
            :breadcrumbs="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Reportes', 'url' => route('admin.reports.index')],
                ['label' => 'Reporte ' . $report->public_id]
            ]" 
        />

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: Report Information -->
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <h2 class="text-base font-bold text-slate-900">Detalles del Reporte</h2>
                        <div class="mt-4 grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <span class="text-slate-400">Fecha de envío</span>
                                <p class="mt-0.5 font-bold text-slate-800">{{ $report->created_at->format('d/m/Y H:i:s') }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Motivo denunciado</span>
                                <p class="mt-0.5 font-bold text-rose-700 uppercase">{{ $report->reason }}</p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Descripción del denunciante</span>
                            <p class="mt-2 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-700 whitespace-pre-line">
                                {{ $report->description ?: 'El usuario no incluyó detalles adicionales.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Target entity inspection -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <h2 class="text-base font-bold text-slate-900">Entidad Reportada</h2>
                        @if ($report->reportable)
                            <div class="mt-4 flex items-start justify-between">
                                <div>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-700">
                                        {{ class_basename($report->reportable_type) }}
                                    </span>
                                    <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $report->reportable->name }}</h3>
                                    @if ($report->reportable instanceof \App\Models\Shop)
                                        <p class="mt-1 text-xs text-slate-500">
                                            Slug: /tienda/{{ $report->reportable->slug }} · WhatsApp: +{{ $report->reportable->whatsapp_country_code }} {{ $report->reportable->whatsapp_number }}
                                        </p>
                                        <div class="mt-2">
                                            <a href="{{ route('shops.show', $report->reportable) }}" target="_blank" class="text-xs font-semibold text-blue-600 hover:underline">
                                                Ver vitrina de la tienda ↗
                                            </a>
                                        </div>
                                    @elseif ($report->reportable instanceof \App\Models\Product)
                                        <p class="mt-1 text-xs text-slate-500">
                                            Tienda: {{ $report->reportable->shop->name }} · Precio: RD$ {{ number_format((float) $report->reportable->price, 0) }}
                                        </p>
                                        <div class="mt-2">
                                            <a href="{{ route('products.show', [$report->reportable->shop, $report->reportable]) }}" target="_blank" class="text-xs font-semibold text-blue-600 hover:underline">
                                                Ver ficha del producto ↗
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    @php
                                        $isTargetActive = $report->reportable instanceof \App\Models\Shop
                                            ? $report->reportable->status === 'active'
                                            : $report->reportable->moderation_status === \App\Enums\ProductModerationStatus::Active;
                                    @endphp

                                    @if ($isTargetActive)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700">
                                            Suspendido
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <p class="mt-3 text-xs text-slate-400">El elemento reportado ya ha sido eliminado del sistema.</p>
                        @endif
                    </div>
                </div>

                <!-- Right: Actions & Resolution -->
                <div class="space-y-6">
                    @if ($report->status === 'open')
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                            <h2 class="text-base font-bold text-slate-900">Resolver Reporte</h2>
                            <p class="text-xs text-slate-500 mt-1">Selecciona la medida correspondiente.</p>

                            <form action="{{ route('admin.reports.resolve', $report) }}" method="POST" class="mt-4 space-y-4">
                                @csrf

                                <div class="space-y-2">
                                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                                        <input type="radio" name="action" value="suspend_target" checked class="mt-0.5 text-rose-600 focus:ring-rose-500">
                                        <div>
                                            <p class="text-xs font-bold text-rose-700">Suspender entidad y resolver</p>
                                            <p class="text-[11px] text-slate-500">Oculta inmediatamente el contenido del público.</p>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                                        <input type="radio" name="action" value="dismiss" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                        <div>
                                            <p class="text-xs font-bold text-slate-800">Desestimar reporte</p>
                                            <p class="text-[11px] text-slate-500">No se detectó infracción. Archiva el reporte sin sanción.</p>
                                        </div>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Notas de resolución (opcional)</label>
                                    <textarea name="notes" rows="2" placeholder="Motivo o comentario interno..." class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-800 focus:border-blue-600"></textarea>
                                </div>

                                <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800">
                                    Aplicar y cerrar reporte
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs text-xs text-slate-600">
                            <h2 class="text-base font-bold text-slate-900">Reporte Resuelto</h2>
                            <p class="mt-2 text-slate-500">
                                Resuelto el <strong>{{ $report->resolved_at?->format('d/m/Y H:i') }}</strong> por <strong>{{ $report->resolvedBy?->name ?? 'Admin' }}</strong>.
                            </p>
                        </div>
                    @endif

                    <!-- Direct toggle quick actions -->
                    @if ($report->reportable)
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Acción Rápida</h3>
                            @if ($report->reportable instanceof \App\Models\Shop)
                                <form action="{{ route('admin.shops.toggle-status', $report->reportable) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        {{ $report->reportable->status === 'active' ? 'Suspender tienda ahora' : 'Reactivar tienda' }}
                                    </button>
                                </form>
                            @elseif ($report->reportable instanceof \App\Models\Product)
                                <form action="{{ route('admin.products.toggle-status', $report->reportable) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        {{ $report->reportable->moderation_status === \App\Enums\ProductModerationStatus::Active ? 'Suspender producto ahora' : 'Reactivar producto' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>
</x-layouts.app>
