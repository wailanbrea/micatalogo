<x-layouts.app title="Verifica tu correo | MiCatalogo">
    <main class="min-h-screen bg-[#0A0F1D] text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-hidden">
        <!-- Ambient background lighting effects -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-96 w-96 rounded-full bg-blue-600/20 blur-[128px]"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-indigo-600/20 blur-[128px]"></div>

        <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 shadow-2xl backdrop-blur-xl grid grid-cols-1 lg:grid-cols-12">
            
            <!-- Left Branding & Guidance Column (Desktop) -->
            <div class="hidden lg:flex lg:col-span-5 flex-col justify-between p-10 bg-gradient-to-br from-slate-900 via-blue-950/40 to-slate-900 border-r border-slate-800/80">
                <div>
                    <a class="inline-flex items-center gap-2 text-2xl font-black tracking-tight text-white hover:opacity-95" href="{{ route('home') }}">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white font-extrabold shadow-md shadow-blue-500/30">M</span>
                        <span>Mi<span class="text-blue-400">Catalogo</span></span>
                    </a>
                    <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400 border border-emerald-500/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Activación de Cuenta
                    </div>

                    <h2 class="mt-8 text-2xl font-bold tracking-tight text-white leading-snug">
                        Tu vitrina digital está a solo un paso de comenzar.
                    </h2>
                    <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                        Para proteger tu negocio y asegurarnos de que solo tú puedas gestionar tus productos y pedidos, confirma tu correo electrónico.
                    </p>

                    <!-- Feature bullets -->
                    <div class="mt-8 space-y-3.5 text-xs text-slate-300">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold">1</span>
                            <span>Revisa el correo que te enviamos (o la carpeta de spam).</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold">2</span>
                            <span>Haz clic en el botón directo o copia el código de 6 dígitos.</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold">3</span>
                            <span>¡Listo! Tu vitrina quedará habilitada al instante.</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs text-slate-400">
                        ¿Abriste el correo en tu teléfono? Puedes pulsar directamente el botón <strong>"Activar mi cuenta y vitrina"</strong> en el correo y entrarás sin escribir el código.
                    </p>
                </div>
            </div>

            <!-- Right Verification Form Column -->
            <div class="lg:col-span-7 p-6 sm:p-10 bg-white text-slate-900 flex flex-col justify-center" x-data="{ userEmail: '{{ old('email', $email) }}' }">
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

                    <!-- Icon & Title -->
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 border border-blue-100 shadow-sm">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Verifica tu correo</h1>
                            <p class="text-xs text-slate-500">Ingresa el código o abre el enlace del mensaje</p>
                        </div>
                    </div>

                    @if ($email)
                        <div class="mt-4 rounded-xl bg-slate-50 p-3.5 text-xs text-slate-600 border border-slate-200">
                            Enviamos un código de activación a <strong class="text-slate-900">{{ $email }}</strong>.
                        </div>
                    @else
                        <p class="mt-4 text-xs text-slate-500">
                            Escribe tu correo y el código de 6 dígitos que te enviamos para activar tu vitrina.
                        </p>
                    @endif

                    @if (session('status') === 'verification-code-sent')
                        <div class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 p-3.5 text-xs font-medium text-emerald-800 border border-emerald-200">
                            <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Hemos enviado un nuevo código y enlace a tu correo. Revisa también tu carpeta de spam.</span>
                        </div>
                    @elseif (session('status'))
                        <div class="mt-4 flex items-center gap-2 rounded-xl bg-blue-50 p-3.5 text-xs font-medium text-blue-800 border border-blue-200">
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <!-- Verification Form -->
                    <form class="mt-6 space-y-4" method="POST" action="{{ route('verification.verify-code') }}">
                        @csrf

                        @if (! $user)
                            <!-- Email Input if not logged in -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="verify_email">
                                    Correo electrónico
                                </label>
                                <div class="relative mt-1.5">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"/></svg>
                                    </span>
                                    <input 
                                        class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                        id="verify_email" 
                                        name="email" 
                                        type="email" 
                                        x-model="userEmail"
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
                        @endif

                        <!-- 6-digit Code Input -->
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="code">
                                    Código de 6 dígitos
                                </label>
                                <span class="text-[11px] text-slate-400">Expira en 15 minutos</span>
                            </div>
                            <div class="mt-1.5">
                                <input 
                                    class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 py-3 text-center text-3xl font-extrabold tracking-[0.4em] text-slate-900 placeholder:text-slate-300 focus:bg-white focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10 transition" 
                                    id="code" 
                                    name="code" 
                                    type="text" 
                                    inputmode="numeric" 
                                    pattern="[0-9]{6}" 
                                    maxlength="6" 
                                    placeholder="------"
                                    autocomplete="one-time-code" 
                                    required 
                                    autofocus>
                            </div>
                            @error('code') 
                                <p class="mt-1.5 text-xs font-semibold text-red-600 flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $message }}
                                </p> 
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <button 
                            class="w-full rounded-xl bg-blue-600 py-3 px-4 text-sm font-bold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700 active:scale-[0.99] transition duration-150 flex items-center justify-center gap-2" 
                            type="submit">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Verificar código y entrar</span>
                        </button>
                    </form>

                    <!-- Resend Code Form -->
                    <div class="mt-6 pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                        <form method="POST" action="{{ route('verification.send-code') }}">
                            @csrf
                            @if (! $user)
                                <input type="hidden" name="email" :value="userEmail">
                            @endif
                            <button class="font-semibold text-blue-600 hover:text-blue-800 transition flex items-center gap-1" type="submit">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Reenviar código</span>
                            </button>
                        </form>

                        @if ($user)
                            <form method="POST" action="{{ url('/logout') }}">
                                @csrf
                                <button class="font-medium text-slate-500 hover:text-slate-800 transition" type="submit">
                                    Cerrar sesión
                                </button>
                            </form>
                        @else
                            <a class="font-medium text-slate-500 hover:text-slate-800 transition" href="{{ route('login') }}">
                                ¿Ya verificaste? Iniciar sesión
                            </a>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </main>
</x-layouts.app>
