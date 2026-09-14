<x-layouts.app title="Acceso no autorizado | MiCatalogo">
    <div class="flex min-h-screen flex-col items-center justify-center bg-[#F5F7FA] px-4 text-center text-slate-800">
        <div class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-xs">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600 mb-4">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">403</h1>
            <h2 class="mt-2 text-lg font-bold text-slate-800">Acceso restringido</h2>
            <p class="mt-2 text-sm text-slate-500">
                No tienes los permisos necesarios para ver o modificar este recurso. Si crees que esto es un error, por favor contacta al administrador.
            </p>
            <div class="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                <a class="rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-blue-700" href="{{ route('home') }}">
                    Ir al inicio
                </a>
                @auth
                    <a class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('seller.dashboard') }}">
                        Mi Panel de Vendedor
                    </a>
                @else
                    <a class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('login') }}">
                        Iniciar sesión
                    </a>
                @endauth
            </div>
        </div>
    </div>
</x-layouts.app>
