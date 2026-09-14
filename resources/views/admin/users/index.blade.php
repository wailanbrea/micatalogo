<x-layouts.app title="Usuarios del Sistema | Panel Admin">
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <!-- Admin Unified Navigation -->
        <x-admin.header 
            :breadcrumbs="[
                ['label' => 'Dashboard Admin', 'url' => route('admin.dashboard')],
                ['label' => 'Usuarios']
            ]" 
        />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <!-- Header & Action Summary -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Usuarios del Sistema</h1>
                    <p class="text-xs text-slate-500 mt-1">Supervisión, auditoría de cuentas, control de acceso y asignación de roles.</p>
                </div>

                <!-- Quick Summary Pills -->
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-slate-700 shadow-2xs">
                        Total: <strong class="text-slate-900">{{ $stats['total'] }}</strong>
                    </span>
                    <span class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-emerald-700 shadow-2xs">
                        Activos: <strong>{{ $stats['active'] }}</strong>
                    </span>
                    @if ($stats['suspended'] > 0)
                        <span class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-1.5 text-rose-700 shadow-2xs">
                            Suspendidos: <strong>{{ $stats['suspended'] }}</strong>
                        </span>
                    @endif
                    <span class="rounded-lg bg-purple-50 border border-purple-200 px-3 py-1.5 text-purple-700 shadow-2xs">
                        Admins: <strong>{{ $stats['admins'] }}</strong>
                    </span>
                </div>
            </div>

            <!-- Flash Alerts -->
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Search & Filters Toolbar -->
            <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-3">
                    <!-- Text Search -->
                    <div class="relative min-w-64 flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="search" 
                            name="q" 
                            value="{{ $search }}" 
                            placeholder="Buscar por nombre o correo electrónico..." 
                            class="w-full rounded-lg border border-slate-300 pl-9 pr-4 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 transition"
                        >
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <select 
                            name="role" 
                            class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-xs text-slate-700 font-medium focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 cursor-pointer"
                            onchange="this.form.submit()"
                        >
                            <option value="">Todos los roles</option>
                            <option value="seller" @selected($role === 'seller')>Vendedores</option>
                            <option value="admin" @selected($role === 'admin')>Administradores</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select 
                            name="status" 
                            class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-xs text-slate-700 font-medium focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600 cursor-pointer"
                            onchange="this.form.submit()"
                        >
                            <option value="">Todos los estados</option>
                            <option value="active" @selected($status === 'active')>Activos</option>
                            <option value="suspended" @selected($status === 'suspended')>Suspendidos</option>
                        </select>
                    </div>

                    <!-- Filter Button -->
                    <button 
                        type="submit" 
                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer shadow-2xs"
                    >
                        Filtrar
                    </button>

                    @if (!empty($search) || !empty($status) || !empty($role))
                        <a 
                            href="{{ route('admin.users.index') }}" 
                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                        >
                            Limpiar
                        </a>
                    @endif
                </form>
            </div>

            <!-- Users Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 font-bold text-slate-600">
                        <tr>
                            <th class="px-4 py-3.5">Usuario</th>
                            <th class="px-4 py-3.5">Rol</th>
                            <th class="px-4 py-3.5">Tiendas</th>
                            <th class="px-4 py-3.5">Verificación</th>
                            <th class="px-4 py-3.5">Registro / Acceso</th>
                            <th class="px-4 py-3.5">Estado</th>
                            <th class="px-4 py-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50 transition">
                                <!-- User Identity -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $user->isAdmin() ? 'bg-rose-100 text-rose-700' : 'bg-blue-100 text-blue-700' }} font-bold text-xs shrink-0">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-900 truncate">
                                                {{ $user->name }}
                                                @if ($user->id === auth()->id())
                                                    <span class="text-[10px] text-blue-600 font-bold ml-1">(Tú)</span>
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-slate-500 font-mono truncate">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($user->isAdmin())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Admin
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[10px] font-semibold text-slate-700">
                                            Vendedor
                                        </span>
                                    @endif
                                </td>

                                <!-- Shops -->
                                <td class="px-4 py-3">
                                    @if ($user->shops_count > 0)
                                        <div class="flex items-center gap-1.5">
                                            <span class="rounded bg-blue-50 px-2 py-0.5 font-bold text-blue-700 text-[11px]">
                                                {{ $user->shops_count }}
                                            </span>
                                            <div class="text-[11px] text-slate-600 truncate max-w-40">
                                                {{ $user->shops->pluck('name')->join(', ') }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-[11px]">Sin tienda</span>
                                    @endif
                                </td>

                                <!-- Verification -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($user->email_verified_at)
                                        <span class="inline-flex items-center gap-1 text-[11px] text-emerald-700 font-medium" title="{{ $user->email_verified_at->toDateTimeString() }}">
                                            <span class="text-emerald-500">✓</span> Verificado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[11px] text-amber-700 font-medium">
                                            <span class="text-amber-500">!</span> Pendiente
                                        </span>
                                    @endif
                                </td>

                                <!-- Dates -->
                                <td class="px-4 py-3 whitespace-nowrap text-[11px] text-slate-500">
                                    <div>Registro: {{ $user->created_at->format('d/m/Y') }}</div>
                                    @if ($user->last_login_at)
                                        <div class="text-slate-400">Acceso: {{ $user->last_login_at->diffForHumans() }}</div>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($user->status->value === 'active')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Suspendido
                                        </span>
                                    @endif
                                </td>

                                <!-- Action Buttons -->
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($user->id !== auth()->id())
                                            <!-- Toggle Status -->
                                            <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" class="inline">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    onclick="return confirm('¿Confirmas que deseas {{ $user->status->value === 'active' ? 'suspender' : 'reactivar' }} a este usuario?')"
                                                    class="rounded-md px-2.5 py-1 text-[11px] font-bold transition shadow-2xs cursor-pointer {{ $user->status->value === 'active' ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200' }}"
                                                >
                                                    {{ $user->status->value === 'active' ? 'Suspender' : 'Activar' }}
                                                </button>
                                            </form>

                                            <!-- Toggle Role -->
                                            <form method="POST" action="{{ route('admin.users.toggle-role', $user) }}" class="inline">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    onclick="return confirm('¿Confirmas cambiar el rol de {{ $user->name }}?')"
                                                    class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs cursor-pointer"
                                                    title="Cambiar entre Administrador y Vendedor"
                                                >
                                                    {{ $user->isAdmin() ? 'Hacer Vendedor' : 'Hacer Admin' }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-400 font-mono">
                                                Cuenta actual
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 text-xs">
                                    No se encontraron usuarios con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </main>
    </div>
</x-layouts.app>
