<x-layouts.app :title="'Activa tu acceso | MiCatalogo'">
    <main class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10 sm:px-6">
        <section class="w-full max-w-md rounded-2xl border border-slate-800 bg-white p-6 shadow-2xl sm:p-8">
            <a class="text-sm font-black tracking-tight text-slate-950" href="{{ route('home') }}">Mi<span class="text-blue-600">Catalogo</span></a>
            <p class="mt-6 text-sm font-semibold text-blue-700">Invitación para {{ $shop->name }}</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Crea tu contraseña</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Confirma tu acceso como {{ $isManager ? 'usuario administrativo' : 'vendedor' }} con <strong>{{ $seller->email }}</strong>. Luego podrás iniciar sesión desde la web o BSPOS.</p>

            <form method="POST" action="{{ $activationUrl }}" class="mt-7 space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-bold text-slate-800" for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="mt-1.5 block w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @error('password') <p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-800" for="password_confirmation">Confirma tu contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1.5 block w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                </div>
                <button class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-black text-white transition hover:bg-blue-700" type="submit">Activar mi acceso</button>
            </form>

            <a class="mt-6 block text-center text-sm font-semibold text-blue-700 hover:text-blue-900" href="{{ route('shops.show', $shop) }}">Ver vitrina de {{ $shop->name }}</a>
        </section>
    </main>
</x-layouts.app>
