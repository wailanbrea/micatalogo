<x-layouts.app title="Iniciar sesión | MiCatalogo">
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
                        Vitrina Digital para Negocios
                    </div>

                    <h2 class="mt-8 text-2xl font-bold tracking-tight text-white leading-snug">
                        Vende por WhatsApp de forma profesional y organizada.
                    </h2>
                    <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                        Publica tu inventario con fotos en alta definición, comparte tu enlace único y atiende pedidos directos sin intermediarios ni comisiones.
                    </p>

                    <!-- Feature bullets -->
                    <div class="mt-8 space-y-3.5 text-xs text-slate-300">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">✓</span>
                            <span>Catálogo digital en WebP con carga instantánea</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">✓</span>
                            <span>Enlaces inteligentes a tu número de WhatsApp</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">✓</span>
                            <span>Precios en Pesos Dominicanos (RD$) y métricas en vivo</span>
                        </div>
                    </div>
                </div>

                <!-- Verified Merchant Preview card -->
                <div class="mt-8 rounded-xl border border-slate-800 bg-slate-950/60 p-4 shadow-inner">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600/20 text-blue-400 font-bold border border-blue-500/30">
                            BS
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-white truncate">BSolutions.dev</h4>
                            <p class="text-[11px] text-slate-400 truncate">Software, SaaS & CRM WhatsApp</p>
                        </div>
                        <span class="rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 border border-emerald-500/20">
                            Activo
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Login Form Column -->
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
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Bienvenido de nuevo</h1>
                        <p class="mt-1.5 text-sm text-slate-500">Ingresa tus credenciales para acceder a tu vitrina.</p>
                    </div>

                    @if (session('status'))
                        <div class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 p-3.5 text-xs font-medium text-emerald-800 border border-emerald-200">
                            <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <form class="mt-6 space-y-4" method="POST" action="{{ url('/login') }}" x-data="{ showPassword: false }">
                        @csrf
                        
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
                                    autofocus 
                                    autocomplete="email">
                            </div>
                            @error('email') 
                                <p class="mt-1.5 text-xs font-semibold text-red-600 flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $message }}
                                </p> 
                            @enderror
                        </div>

                        <!-- Password Input with Toggle -->
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="password">
                                    Contraseña
                                </label>
                                <a class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition" href="{{ url('/forgot-password') }}">
                                    ¿Olvidaste tu contraseña?
                                </a>
                            </div>
                            <div class="relative mt-1.5">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </span>
                                <input 
                                    class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                    id="password" 
                                    name="password" 
                                    :type="showPassword ? 'text' : 'password'" 
                                    placeholder="••••••••"
                                    required 
                                    autocomplete="current-password">
                                <button 
                                    type="button" 
                                    @click="showPassword = !showPassword" 
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 focus:outline-none"
                                    aria-label="Alternar visibilidad de contraseña">
                                    <svg x-show="!showPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="showPassword" x-cloak class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                </button>
                            </div>
                            @error('password') 
                                <p class="mt-1.5 text-xs font-semibold text-red-600 flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $message }}
                                </p> 
                            @enderror
                        </div>

                        <!-- Turnstile Widget with neat container -->
                        @if (config('services.turnstile.enabled'))
                            <div class="pt-1">
                                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 flex justify-center">
                                    <div class="cf-turnstile" data-action="login" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                                </div>
                                @error('cf-turnstile-response') 
                                    <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> 
                                @enderror
                            </div>
                        @endif

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-600 cursor-pointer select-none">
                                <input class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600" name="remember" type="checkbox">
                                <span>Recordar sesión en este dispositivo</span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 py-3 px-4 text-sm font-bold text-white shadow-md shadow-blue-500/20 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20 active:scale-[0.99] transition duration-150 cursor-pointer" type="submit">
                            Iniciar sesión
                        </button>
                    </form>

                    <!-- Bottom Signup Link -->
                    <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                        <p class="text-xs text-slate-500">
                            ¿Aún no tienes tu catálogo?
                            <a class="font-bold text-blue-600 hover:text-blue-800 transition ml-1" href="{{ url('/register') }}">
                                Crea tu tienda gratis aquí →
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

