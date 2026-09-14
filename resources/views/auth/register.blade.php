<x-layouts.app title="Crea tu catálogo gratis | MiCatalogo">
    <main class="min-h-screen bg-[#0A0F1D] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-hidden">
        <!-- Ambient background lighting effects -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-96 w-96 rounded-full bg-blue-600/20 blur-[128px]"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-indigo-600/20 blur-[128px]"></div>

        <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 shadow-2xl backdrop-blur-xl grid grid-cols-1 lg:grid-cols-12">
            
            <!-- Left Branding & Value Proposition Column (Desktop) -->
            <div class="hidden lg:flex lg:col-span-5 flex-col justify-between p-10 bg-gradient-to-br from-slate-900 via-blue-950/40 to-slate-900 border-r border-slate-800/80">
                <div>
                    <a class="inline-flex items-center gap-2 text-2xl font-black tracking-tight text-white hover:opacity-95" href="{{ route('home') }}">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white font-extrabold shadow-md shadow-blue-500/30">M</span>
                        <span>Mi<span class="text-blue-400">Catalogo</span></span>
                    </a>
                    <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-400 border border-blue-500/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                        Plan Gratuito Permanente
                    </div>

                    <h2 class="mt-8 text-2xl font-bold tracking-tight text-white leading-snug">
                        Comienza a vender en línea en menos de 3 minutos.
                    </h2>
                    <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                        Crea tu tienda digital, sube hasta 100 productos con fotos WebP y comparte un enlace limpio con tus clientes de WhatsApp.
                    </p>

                    <!-- Free plan highlights -->
                    <div class="mt-8 space-y-3.5 text-xs text-slate-300">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">✓</span>
                            <span>1 tienda activa y hasta 100 productos</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">✓</span>
                            <span>Subida masiva de fotos por lote</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">✓</span>
                            <span>Cero comisiones por venta o consulta</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs text-slate-400">
                        "MiCatalogo me permitió organizar más de 50 productos en una sola tarde y compartirlos por WhatsApp sin enredos."
                    </p>
                    <p class="mt-2 text-[11px] font-bold text-slate-300">Comerciante de calzado y moda · Santo Domingo</p>
                </div>
            </div>

            <!-- Right Register Form Column -->
            <div class="lg:col-span-7 p-6 sm:p-10 bg-white text-slate-900 flex flex-col justify-center">
                <div class="max-w-md w-full mx-auto">
                    
                    <div class="flex items-center justify-between mb-6">
                        <a class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-blue-600 transition" href="{{ route('home') }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            <span>Volver a MiCatalogo</span>
                        </a>
                        <div class="lg:hidden">
                            <a class="text-base font-black text-slate-900" href="{{ route('home') }}">
                                Mi<span class="text-blue-600">Catalogo</span>
                            </a>
                        </div>
                    </div>

                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Crea tu catálogo gratis</h1>
                        <p class="mt-1.5 text-sm text-slate-500">Regístrate para abrir tu vitrina digital en República Dominicana.</p>
                    </div>

                    <form class="mt-6 space-y-4" method="POST" action="{{ url('/register') }}" x-data="{ showPass: false }">
                        @csrf
                        
                        <!-- Name Input -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="name">
                                Tu Nombre o Nombre del Negocio
                            </label>
                            <div class="relative mt-1.5">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </span>
                                <input 
                                    class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                    id="name" 
                                    name="name" 
                                    type="text" 
                                    value="{{ old('name') }}" 
                                    placeholder="Ej. Juan Pérez o Zapatería Pérez"
                                    required 
                                    autofocus 
                                    autocomplete="name">
                            </div>
                            @error('name') 
                                <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> 
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
                                    class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                    id="email" 
                                    name="email" 
                                    type="email" 
                                    value="{{ old('email') }}" 
                                    placeholder="tu@correo.com"
                                    required 
                                    autocomplete="email">
                            </div>
                            @error('email') 
                                <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> 
                            @enderror
                        </div>

                        <!-- Password Input -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="password">
                                    Contraseña
                                </label>
                                <div class="relative mt-1.5">
                                    <input 
                                        class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                        id="password" 
                                        name="password" 
                                        :type="showPass ? 'text' : 'password'" 
                                        placeholder="Mínimo 8 caracteres"
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
                                        class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                        id="password_confirmation" 
                                        name="password_confirmation" 
                                        :type="showPass ? 'text' : 'password'" 
                                        placeholder="Repite la contraseña"
                                        required 
                                        autocomplete="new-password">
                                </div>
                            </div>
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
                        <button class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 py-3 px-4 text-sm font-bold text-white shadow-md shadow-blue-500/20 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20 active:scale-[0.99] transition duration-150 cursor-pointer" type="submit">
                            Crear mi cuenta y vitrina gratis
                        </button>
                    </form>

                    <!-- Bottom Login Link -->
                    <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                        <p class="text-xs text-slate-500">
                            ¿Ya tienes una cuenta registrada?
                            <a class="font-bold text-blue-600 hover:text-blue-800 transition ml-1" href="{{ url('/login') }}">
                                Inicia sesión aquí →
                            </a>
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </main>

    @if (config('services.turnstile.enabled'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</x-layouts.app>

