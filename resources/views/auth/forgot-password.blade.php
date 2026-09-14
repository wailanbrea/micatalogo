<x-layouts.app title="Recuperar contrasena">
    <main class="min-h-screen bg-slate-50 px-4 py-12 sm:py-20">
        <div class="mx-auto w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <a class="text-lg font-bold text-slate-900" href="{{ route('home') }}">Mi<span class="text-blue-600">Catalogo</span></a>
            <h1 class="mt-8 text-2xl font-bold text-slate-900">Recupera tu contrasena</h1>
            <p class="mt-2 text-sm text-slate-600">Te enviaremos un enlace seguro para crear una nueva contrasena.</p>
            @if (session('status')) <p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p> @endif
            <form class="mt-6 space-y-5" method="POST" action="{{ url('/forgot-password') }}">
                @csrf
                <div>
                    <label class="text-sm font-medium text-slate-700" for="email">Correo electronico</label>
                    <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700" type="submit">Enviar enlace</button>
            </form>
        </div>
    </main>
</x-layouts.app>
