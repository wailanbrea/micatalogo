<x-layouts.app title="Nueva contrasena">
    <main class="min-h-screen bg-slate-50 px-4 py-12 sm:py-20">
        <div class="mx-auto w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold text-slate-900">Crea una nueva contrasena</h1>
            <form class="mt-6 space-y-5" method="POST" action="{{ url('/reset-password') }}">
                @csrf
                <input name="token" type="hidden" value="{{ $request->route('token') }}">
                <div>
                    <label class="text-sm font-medium text-slate-700" for="email">Correo electronico</label>
                    <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="email">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password">Nueva contrasena</label>
                    <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="password" name="password" type="password" required autocomplete="new-password">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password_confirmation">Confirmar contrasena</label>
                    <input class="mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
                <button class="w-full rounded-md bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700" type="submit">Actualizar contrasena</button>
            </form>
        </div>
    </main>
</x-layouts.app>
