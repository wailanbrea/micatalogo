<x-layouts.app title="Cola de Moderación | MiCatalogo Admin">
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <!-- Unified Admin Header with Breadcrumbs & Back Navigation -->
        <x-admin.header 
            :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Reportes']]" 
        />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Cola de Moderación</h1>
                    <p class="text-xs text-slate-500 mt-1">Revisión y resolución de reportes de contenido generados por usuarios.</p>
                </div>

                <!-- Status Filter Pills -->
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <a class="rounded-full px-3 py-1.5 transition {{ $currentStatus === 'open' ? 'bg-rose-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}" href="{{ route('admin.reports.index', ['status' => 'open']) }}">
                        Abiertos
                    </a>
                    <a class="rounded-full px-3 py-1.5 transition {{ $currentStatus === 'resolved' ? 'bg-blue-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}" href="{{ route('admin.reports.index', ['status' => 'resolved']) }}">
                        Resueltos
                    </a>
                    <a class="rounded-full px-3 py-1.5 transition {{ $currentStatus === 'all' ? 'bg-slate-800 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}" href="{{ route('admin.reports.index', ['status' => 'all']) }}">
                        Todos
                    </a>
                </div>
            </div>

            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Reports Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 font-bold text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Entidad reportada</th>
                            <th class="px-4 py-3">Motivo</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Resolución</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($reports as $report)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">
                                    {{ $report->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-bold uppercase text-[10px] text-slate-700">
                                        {{ class_basename($report->reportable_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900">
                                    {{ $report->reportable?->name ?? 'Elemento eliminado' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-bold text-rose-700">
                                    {{ $report->reason }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($report->status === 'open')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 font-bold text-rose-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Pendiente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Resuelto
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">
                                    @if ($report->resolved_at)
                                        {{ $report->resolved_at->format('d/m/Y') }} por {{ $report->resolvedBy?->name ?? 'Admin' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a href="{{ route('admin.reports.show', $report) }}" class="rounded bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100">
                                        Revisar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    No se encontraron reportes con el filtro seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reports->hasPages())
                <div class="mt-6">
                    {{ $reports->links() }}
                </div>
            @endif
        </main>
    </div>
</x-layouts.app>
