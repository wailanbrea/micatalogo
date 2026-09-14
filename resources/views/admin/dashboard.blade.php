<x-layouts.app title="Panel de Administración | MiCatalogo">
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <!-- Admin Unified Navigation -->
        <x-admin.header />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Dashboard del Sistema</h1>
                    <p class="text-xs text-slate-500 mt-1">Supervisión general de catálogo, usuarios, tráfico global y moderación.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a 
                        href="{{ route('admin.users.index') }}" 
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                    >
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Gestionar usuarios</span>
                    </a>
                </div>
            </div>

            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <!-- KPI Metric Cards Grid (2 rows of 3) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- 1. Tiendas -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tiendas</span>
                            <span class="rounded-full bg-blue-50 p-2 text-blue-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $stats['shops']['total'] }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs border-t border-slate-100 pt-2.5">
                        <div class="flex items-center gap-2 text-slate-500">
                            <span class="text-emerald-700 font-semibold">{{ $stats['shops']['active'] }} activas</span>
                            <span>·</span>
                            <span class="{{ $stats['shops']['suspended'] > 0 ? 'text-rose-600 font-bold' : '' }}">{{ $stats['shops']['suspended'] }} susp.</span>
                        </div>
                        <a href="{{ route('seller.dashboard', ['view' => 'all']) }}" class="text-blue-600 font-semibold hover:underline">
                            Ver todas →
                        </a>
                    </div>
                </div>

                <!-- 2. Productos -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Productos</span>
                            <span class="rounded-full bg-emerald-50 p-2 text-emerald-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $stats['products']['total'] }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs border-t border-slate-100 pt-2.5">
                        <div class="flex items-center gap-2 text-slate-500">
                            <span class="text-emerald-700 font-semibold">{{ $stats['products']['active'] }} activos</span>
                            <span>·</span>
                            <span class="text-amber-700">{{ $stats['products']['out_of_stock'] }} agotados</span>
                        </div>
                        <span class="{{ $stats['products']['suspended'] > 0 ? 'text-rose-600 font-bold' : 'text-slate-400' }}">{{ $stats['products']['suspended'] }} susp.</span>
                    </div>
                </div>

                <!-- 3. Usuarios Registrados -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Usuarios</span>
                            <span class="rounded-full bg-indigo-50 p-2 text-indigo-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $stats['users']['total'] }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs border-t border-slate-100 pt-2.5">
                        <div class="flex items-center gap-2 text-slate-500">
                            <span class="text-emerald-700 font-semibold">{{ $stats['users']['active'] }} activos</span>
                            <span>·</span>
                            <span class="text-purple-700 font-semibold">{{ $stats['users']['admins'] }} admin</span>
                        </div>
                        <a href="{{ route('admin.users.index') }}" class="text-indigo-600 font-semibold hover:underline">
                            Gestionar →
                        </a>
                    </div>
                </div>

                <!-- 4. Tráfico Global Plataforma (30 días) -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tráfico Global (30d)</span>
                            <span class="rounded-full bg-teal-50 p-2 text-teal-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <p class="text-3xl font-extrabold text-slate-900">{{ number_format($stats['traffic']['views_30d']) }}</p>
                            <span class="text-xs text-slate-500 font-medium">visitas</span>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs border-t border-slate-100 pt-2.5">
                        <div class="flex items-center gap-1.5 text-slate-500">
                            <span class="font-bold text-emerald-700">{{ number_format($stats['traffic']['clicks_30d']) }}</span> clics WhatsApp
                        </div>
                        <span class="font-bold text-slate-700 font-mono">{{ $stats['traffic']['conversion_rate_30d'] }}% conv.</span>
                    </div>
                </div>

                <!-- 5. Moderación -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Moderación</span>
                            <span class="rounded-full bg-rose-50 p-2 text-rose-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $stats['reports']['open'] }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs border-t border-slate-100 pt-2.5">
                        <span class="{{ $stats['reports']['open'] > 0 ? 'text-rose-600 font-bold' : 'text-slate-600' }}">
                            {{ $stats['reports']['open'] }} pendientes de revisión
                        </span>
                        <a href="{{ route('admin.reports.index') }}" class="text-blue-600 font-semibold hover:underline">
                            Ver cola →
                        </a>
                    </div>
                </div>

                <!-- 6. Storage & Media R2 -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Almacenamiento R2</span>
                            <span class="rounded-full bg-amber-50 p-2 text-amber-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $stats['media']['storage_mb'] }} <span class="text-sm font-medium text-slate-500">MB</span></p>
                    </div>
                    <div class="mt-4 flex items-center justify-between text-xs border-t border-slate-100 pt-2.5 text-slate-500">
                        <span>{{ $stats['media']['total_images'] }} imágenes</span>
                        <span class="{{ $stats['media']['failed_images'] > 0 ? 'text-rose-600 font-bold' : '' }}">{{ $stats['media']['failed_images'] }} fallidas</span>
                    </div>
                </div>
            </div>

            <!-- Recent Open Reports Section -->
            <section class="mt-8">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Reportes pendientes de revisión</h2>
                        <p class="text-xs text-slate-500">Contenido marcado por usuarios para revisión de moderación.</p>
                    </div>
                    <a href="{{ route('admin.reports.index') }}" class="text-xs font-bold text-blue-600 hover:underline">
                        Ver todos los reportes →
                    </a>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 font-bold text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Elemento reportado</th>
                                <th class="px-4 py-3">Motivo</th>
                                <th class="px-4 py-3">Detalle</th>
                                <th class="px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse ($recentReports as $report)
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
                                    <td class="whitespace-nowrap px-4 py-3 text-rose-700 font-bold">
                                        {{ $report->reason }}
                                    </td>
                                    <td class="max-w-xs truncate px-4 py-3 text-slate-500">
                                        {{ $report->description ?: 'Sin comentarios adicionales' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <a href="{{ route('admin.reports.show', $report) }}" class="rounded bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100">
                                            Revisar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">
                                        No hay reportes pendientes de moderación en este momento.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</x-layouts.app>
