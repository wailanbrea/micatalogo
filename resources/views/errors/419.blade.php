<x-layouts.app title="Sesión caducada | MiCatalogo">
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8">
        <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <h1 class="text-xl font-bold text-slate-900">Tu sesión ha caducado</h1>
            <p class="mt-3 text-sm text-slate-600">Esta acción no se procesó porque el formulario caducó. Abre una página nueva y revisa los datos antes de volver a enviarlos.</p>
            <a class="mt-6 inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white" href="{{ auth()->check() ? url('/panel') : url('/login') }}">{{ auth()->check() ? 'Volver al panel' : 'Iniciar sesión' }}</a>
        </section>
    </div>
</x-layouts.app>
