<x-layouts.app title="Error del servidor | MiCatalogo">
    <div class="flex min-h-screen flex-col items-center justify-center bg-[#F5F7FA] px-4 text-center text-slate-800">
        <div class="max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-xs">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600 mb-4">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">500</h1>
            <h2 class="mt-2 text-lg font-bold text-slate-800">Ocurrió un problema inesperado</h2>
            <p class="mt-2 text-sm text-slate-500">
                Hemos registrado el incidente en el sistema para corregirlo. Por favor intenta recargar la página en unos momentos.
            </p>
            <div class="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                <a class="rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-blue-700" href="{{ route('home') }}">
                    Volver al inicio
                </a>
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" onclick="window.location.reload()">
                    Reintentar
                </button>
            </div>
        </div>
    </div>
</x-layouts.app>
