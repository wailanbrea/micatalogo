<x-layouts.app title="Página no encontrada | MiCatalogo">
    <div class="flex min-h-screen flex-col items-center justify-center bg-[#F5F7FA] px-4 text-center text-slate-800">
        <div class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-xs">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600 mb-4">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">404</h1>
            <h2 class="mt-2 text-lg font-bold text-slate-800">Página o producto no disponible</h2>
            <p class="mt-2 text-sm text-slate-500">
                El enlace que seguiste no existe o la tienda/producto ha sido suspendido o eliminado de la plataforma.
            </p>
            <div class="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                <a class="rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-blue-700" href="{{ route('home') }}">
                    Explorar el catálogo
                </a>
                @auth
                    <a class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('seller.dashboard') }}">
                        Ir a mis tiendas
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
