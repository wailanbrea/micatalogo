<x-layouts.app title="Verifica tu correo">
    <main class="min-h-screen bg-slate-50 px-4 py-12 sm:py-20">
        <div class="mx-auto w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold text-slate-900">Verifica tu correo</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Enviamos un enlace de verificacion a tu correo. Debes confirmarlo para gestionar tu catalogo.</p>
            @if (session('status') === 'verification-link-sent') <p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">Enviamos un nuevo enlace de verificacion.</p> @endif
            <form class="mt-6" method="POST" action="{{ url('/email/verification-notification') }}">
                @csrf
                <button class="w-full rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700" type="submit">Reenviar enlace</button>
            </form>
            <form class="mt-3 text-center" method="POST" action="{{ url('/logout') }}">
                @csrf
                <button class="text-sm font-medium text-slate-600 hover:text-slate-900" type="submit">Cerrar sesion</button>
            </form>
        </div>
    </main>
</x-layouts.app>
