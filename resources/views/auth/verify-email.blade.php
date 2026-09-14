<x-layouts.app title="Verifica tu correo">
    <main class="min-h-screen bg-slate-50 px-4 py-12 sm:py-20">
        <div class="mx-auto w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold text-slate-900">Verifica tu correo</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Enviamos un código de 6 dígitos a <strong>{{ auth()->user()->email }}</strong>. Confírmalo para gestionar tu catálogo.</p>
            @if (session('status') === 'verification-code-sent') <p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">Enviamos un nuevo código de verificación.</p> @endif
            <form class="mt-6 space-y-4" method="POST" action="{{ route('verification.verify-code') }}">
                @csrf
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="code">Código de verificación</label>
                <input class="block w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-2xl font-bold tracking-[0.35em] text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                @error('code') <p class="text-sm font-semibold text-red-600">{{ $message }}</p> @enderror
                <button class="w-full rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700" type="submit">Verificar correo</button>
            </form>
            <form class="mt-4 text-center" method="POST" action="{{ route('verification.send-code') }}">
                @csrf
                <button class="text-sm font-semibold text-blue-600 hover:text-blue-800" type="submit">Reenviar código</button>
            </form>
            <form class="mt-3 text-center" method="POST" action="{{ url('/logout') }}">
                @csrf
                <button class="text-sm font-medium text-slate-600 hover:text-slate-900" type="submit">Cerrar sesion</button>
            </form>
        </div>
    </main>
</x-layouts.app>
