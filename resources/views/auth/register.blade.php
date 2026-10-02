<x-layouts.app title="Crea tu catálogo gratis | MiCatalogo">
    <main class="min-h-screen bg-[#F8FAFC] text-slate-900 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        <!-- Ambient background soft lighting -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-96 w-96 rounded-full bg-blue-100/70 blur-[128px]"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-indigo-100/60 blur-[128px]"></div>

        <div class="relative w-full max-w-lg mx-auto">
            <!-- Header & Brand Navigation -->
            <div class="flex items-center justify-between mb-8">
                <a class="inline-flex items-center gap-2 text-slate-600 hover:text-blue-600 text-xs font-semibold transition" href="{{ route('home') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Volver al inicio</span>
                </a>
                <a class="flex items-center gap-2 group text-decoration-none" href="{{ route('home') }}">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm font-black text-sm">
                        M
                    </div>
                    <span class="text-base font-extrabold tracking-tight text-slate-900">
                        Mi<span class="text-blue-600">Catalogo</span>
                    </span>
                </a>
            </div>

            <!-- Elevated Auth Card -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-7 sm:p-9 shadow-xl shadow-slate-200/60 backdrop-blur-sm">
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200 mb-3">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Plan Gratuito Permanente
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Crea tu catálogo gratis</h1>
                    <p class="mt-1.5 text-xs sm:text-sm text-slate-500">Abre tu vitrina digital en menos de 3 minutos sin tarjeta de crédito.</p>
                </div>

                <form class="mt-6 space-y-4.5" method="POST" action="{{ url('/register') }}" x-data="{ showPass: false }">
                    @csrf
                    
                    <!-- Name Input -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="name">
                            Tu Nombre o Nombre de tu Negocio
                        </label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </span>
                            <input 
                                class="block w-full rounded-xl border border-slate-300 bg-slate-50/40 py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 hover:bg-white focus:bg-white focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                id="name" 
                                name="name" 
                                type="text" 
                                value="{{ old('name') }}" 
                                placeholder="Ej. BSolutions o Calzados Pérez"
                                required 
                                autofocus 
                                autocomplete="name">
                        </div>
                        @error('name') 
                            <p class="mt-1.5 text-xs font-semibold text-red-600 flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $message }}
                            </p> 
                        @enderror
                    </div>

                    <!-- Email Input -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="email">
                            Correo electrónico
                        </label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"/></svg>
                            </span>
                            <input 
                                class="block w-full rounded-xl border border-slate-300 bg-slate-50/40 py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 hover:bg-white focus:bg-white focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                id="email" 
                                name="email" 
                                type="email" 
                                value="{{ old('email') }}" 
                                placeholder="tu@correo.com"
                                required 
                                autocomplete="email">
                        </div>
                        @error('email') 
                            <p class="mt-1.5 text-xs font-semibold text-red-600 flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $message }}
                            </p> 
                        @enderror
                    </div>

                    <!-- Password and Confirmation -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="password">
                                Contraseña
                            </label>
                            <div class="relative mt-1.5">
                                <input 
                                    class="block w-full rounded-xl border border-slate-300 bg-slate-50/40 py-2.5 px-3.5 text-sm text-slate-900 placeholder:text-slate-400 hover:bg-white focus:bg-white focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                    id="password" 
                                    name="password" 
                                    :type="showPass ? 'text' : 'password'" 
                                    placeholder="Mín. 8 caracteres"
                                    required 
                                    autocomplete="new-password">
                            </div>
                            @error('password') 
                                <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> 
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="password_confirmation">
                                Confirmar Contraseña
                            </label>
                            <div class="relative mt-1.5">
                                <input 
                                    class="block w-full rounded-xl border border-slate-300 bg-slate-50/40 py-2.5 px-3.5 text-sm text-slate-900 placeholder:text-slate-400 hover:bg-white focus:bg-white focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                    id="password_confirmation" 
                                    name="password_confirmation" 
                                    :type="showPass ? 'text' : 'password'" 
                                    placeholder="Repite la clave"
                                    required 
                                    autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <!-- Show Password Checkbox -->
                    <div class="flex items-center gap-2 pt-0.5">
                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                            <input class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-600" type="checkbox" @change="showPass = !showPass">
                            <span>Mostrar contraseñas</span>
                        </label>
                    </div>

                    <!-- Turnstile Widget -->
                    @if (config('services.turnstile.enabled'))
                        <div class="pt-1">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 flex justify-center">
                                <div class="cf-turnstile" data-action="register" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                            </div>
                            @error('cf-turnstile-response') 
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> 
                            @enderror
                        </div>
                    @endif

                    <!-- Submit Button -->
                    <button class="w-full rounded-xl bg-blue-600 py-3 px-4 text-sm font-bold text-white shadow-md shadow-blue-500/20 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20 active:scale-[0.99] transition duration-150 cursor-pointer" type="submit">
                        Crear mi cuenta y vitrina gratis
                    </button>
                </form>

                <!-- Value Highlights under form -->
                <div class="mt-6 pt-5 border-t border-slate-100 grid grid-cols-3 gap-2 text-center text-[11px] text-slate-600">
                    <div class="p-2 rounded-lg bg-slate-50">
                        <span class="block font-bold text-slate-900">100% Gratis</span>
                        <span>Sin comisiones</span>
                    </div>
                    <div class="p-2 rounded-lg bg-slate-50">
                        <span class="block font-bold text-slate-900">Enlace Único</span>
                        <span>Para tu WhatsApp</span>
                    </div>
                    <div class="p-2 rounded-lg bg-slate-50">
                        <span class="block font-bold text-slate-900">Vitrina Aislada</span>
                        <span>Sin competidores</span>
                    </div>
                </div>

                <!-- Bottom Login Link -->
                <div class="mt-6 pt-4 text-center">
                    <p class="text-xs text-slate-500">
                        ¿Ya tienes una cuenta registrada?
                        <a class="font-bold text-blue-600 hover:text-blue-800 transition ml-1" href="{{ url('/login') }}">
                            Inicia sesión aquí →
                        </a>
                    </p>
                </div>
            </div>

            <!-- Institutional Micro Footer -->
            <div class="mt-6 text-center text-[11px] text-slate-400">
                <span>🔒 Registro rápido y seguro · Diseñado para el comercio independiente en RD</span>
            </div>
        </div>
    </main>

    @if (config('services.turnstile.enabled'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</x-layouts.app>
