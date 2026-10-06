@php
    $step1Fields = ['name', 'email'];
    $step2Fields = ['business_name', 'business_type'];
    $step3Fields = ['whatsapp_country_code', 'whatsapp_number', 'slug', 'instagram'];
    $step4Fields = ['password', 'password_confirmation', 'terms_accepted', 'cf-turnstile-response'];
    $initialStep = $errors->hasAny($step4Fields) ? 4 : ($errors->hasAny($step3Fields) ? 3 : ($errors->hasAny($step2Fields) ? 2 : 1));
@endphp

<x-layouts.app title="Crea tu tienda gratis | MiCatalogo">
    <main class="min-h-screen bg-slate-50 px-4 py-8 text-slate-900 sm:px-6 lg:px-8">
        <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl items-center gap-8 lg:grid-cols-[.8fr_1.2fr]">
            <section class="hidden rounded-3xl bg-slate-950 p-10 text-white shadow-2xl shadow-slate-300 lg:block">
                <a class="inline-flex items-center gap-2 text-xl font-black tracking-tight" href="{{ route('home') }}">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm">M</span>
                    Mi<span class="text-blue-400">Catalogo</span>
                </a>
                <p class="mt-16 text-sm font-bold uppercase tracking-[.22em] text-blue-300">Tu negocio, en orden</p>
                <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight">Crea tu tienda y empieza a vender por WhatsApp.</h1>
                <p class="mt-5 max-w-md text-sm leading-7 text-slate-300">Te guiaremos paso a paso para dejar lista tu vitrina, inventario y datos de contacto. Puedes completar los detalles visuales después.</p>
                <div class="mt-10 space-y-4 text-sm text-slate-200">
                    <div class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 font-bold">1</span> Configura tu perfil y negocio</div>
                    <div class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 font-bold">2</span> Comparte tu catálogo</div>
                    <div class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 font-bold">3</span> Controla ventas, stock y caja</div>
                </div>
            </section>

            <section class="mx-auto w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/60 sm:p-8" x-data="{ step: {{ $initialStep }}, maxStep: 4, next() { const panel = this.$root.querySelector(`[data-step='${this.step}']`); const required = panel ? [...panel.querySelectorAll('[required]')] : []; const invalid = required.find((field) => !field.checkValidity()); if (invalid) { invalid.reportValidity(); return; } this.step = Math.min(this.maxStep, this.step + 1); window.scrollTo({ top: 0, behavior: 'smooth' }); }, previous() { this.step = Math.max(1, this.step - 1); window.scrollTo({ top: 0, behavior: 'smooth' }); } }">
                <div class="flex items-center justify-between gap-4">
                    <a class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 transition hover:text-blue-700" href="{{ route('home') }}">← Volver al inicio</a>
                    <a class="flex items-center gap-2 text-sm font-black tracking-tight text-slate-900" href="{{ route('home') }}"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-xs text-white">M</span>Mi<span class="text-blue-600">Catalogo</span></a>
                </div>

                <div class="mt-8">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-[.2em] text-blue-700">Registro guiado</span>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Crea tu tienda gratis</h2>
                            <p class="mt-2 text-sm text-slate-500">Completa estos pasos y tendrás tu espacio listo para vender.</p>
                        </div>
                        <span class="text-xs font-bold text-slate-400"><span x-text="step"></span> / 4</span>
                    </div>
                    <div class="mt-6 grid grid-cols-4 gap-2" aria-label="Progreso del registro">
                        <template x-for="item in [1,2,3,4]" :key="item"><span class="h-1.5 rounded-full" :class="item <= step ? 'bg-blue-600' : 'bg-slate-200'"></span></template>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
                        <p class="font-bold">Revisa la información indicada:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form class="mt-8" method="POST" action="{{ url('/register') }}">
                    @csrf

                    <section data-step="1" x-show="step === 1" x-cloak>
                        <div class="rounded-2xl bg-blue-50 p-4 text-sm text-blue-900"><strong>Primero, cuéntanos de ti.</strong><br><span class="text-blue-800/80">Usaremos estos datos para identificar al dueño de la cuenta.</span></div>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="text-sm font-bold text-slate-800" for="name">Tu nombre</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Ej. Wailan Brea" required autofocus autocomplete="name"></div>
                            <div class="sm:col-span-2"><label class="text-sm font-bold text-slate-800" for="email">Correo electrónico</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="tu@correo.com" required autocomplete="email"></div>
                        </div>
                    </section>

                    <section data-step="2" x-show="step === 2" x-cloak>
                        <div class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900"><strong>Ahora configuremos tu negocio.</strong><br><span class="text-emerald-800/80">Esto adapta categorías y funciones para que no veas opciones que no necesitas.</span></div>
                        <div class="mt-5 space-y-5">
                            <div><label class="text-sm font-bold text-slate-800" for="business_name">Nombre del negocio</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="business_name" name="business_name" type="text" value="{{ old('business_name') }}" placeholder="Ej. Casa Nativa" required autocomplete="organization"></div>
                            <div><label class="text-sm font-bold text-slate-800" for="business_type">Tipo de negocio</label><select class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="business_type" name="business_type" required><option value="">Selecciona el tipo de negocio</option>@foreach (app(\App\Services\BusinessProfileService::class)->types() as $key => $profile)<option value="{{ $key }}" @selected(old('business_type') === $key)>{{ $profile['label'] }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Puedes ajustar categorías, diseño y detalles desde tu panel después.</p></div>
                        </div>
                    </section>

                    <section data-step="3" x-show="step === 3" x-cloak>
                        <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><strong>Define cómo te encontrarán.</strong><br><span class="text-amber-800/80">Tu WhatsApp se usará para recibir pedidos y tu enlace será único.</span></div>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div><label class="text-sm font-bold text-slate-800" for="whatsapp_country_code">Código de país</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="whatsapp_country_code" name="whatsapp_country_code" type="text" value="{{ old('whatsapp_country_code', '1809') }}" inputmode="numeric" maxlength="5" required></div>
                            <div><label class="text-sm font-bold text-slate-800" for="whatsapp_number">Número de WhatsApp</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="whatsapp_number" name="whatsapp_number" type="tel" value="{{ old('whatsapp_number') }}" placeholder="8298144525" inputmode="numeric" required autocomplete="tel"></div>
                            <div class="sm:col-span-2"><label class="text-sm font-bold text-slate-800" for="slug">Enlace de tu tienda <span class="font-normal text-slate-400">(opcional)</span></label><div class="mt-2 flex items-center overflow-hidden rounded-xl border border-slate-300 bg-white"><span class="px-3 text-xs text-slate-400">/tienda/</span><input class="min-w-0 flex-1 border-0 px-2 py-3 text-sm outline-none focus:ring-0" id="slug" name="slug" type="text" value="{{ old('slug') }}" placeholder="mi-negocio" autocomplete="off"></div></div>
                            <div class="sm:col-span-2"><label class="text-sm font-bold text-slate-800" for="instagram">Instagram <span class="font-normal text-slate-400">(opcional)</span></label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="instagram" name="instagram" type="text" value="{{ old('instagram') }}" placeholder="@mi.negocio" autocomplete="off"></div>
                        </div>
                    </section>

                    <section data-step="4" x-show="step === 4" x-cloak>
                        <div class="rounded-2xl bg-slate-100 p-4 text-sm text-slate-800"><strong>Protege tu cuenta.</strong><br><span class="text-slate-600">Crea una contraseña segura. El plan gratis se asigna automáticamente al registrarte.</span></div>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div><label class="text-sm font-bold text-slate-800" for="password">Contraseña</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="password" name="password" type="password" placeholder="Mínimo 8 caracteres" required autocomplete="new-password"></div>
                            <div><label class="text-sm font-bold text-slate-800" for="password_confirmation">Confirmar contraseña</label><input class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" id="password_confirmation" name="password_confirmation" type="password" placeholder="Repite la contraseña" required autocomplete="new-password"></div>
                        </div>
                        <label class="mt-5 flex items-start gap-3 text-sm text-slate-600"><input class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600" type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required><span>Acepto los términos de uso y la política de privacidad de MiCatalogo.</span></label>
                        @if (config('services.turnstile.enabled'))<div class="mt-5 flex justify-center rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="cf-turnstile" data-action="register" data-sitekey="{{ config('services.turnstile.site_key') }}"></div></div>@endif
                    </section>

                    <div class="mt-8 flex items-center justify-between gap-3 border-t border-slate-100 pt-5">
                        <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" type="button" @click="previous()" :disabled="step === 1">Atrás</button>
                        <button x-show="step < maxStep" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700" type="button" @click="next()">Continuar <span aria-hidden="true">→</span></button>
                        <button x-show="step === maxStep" x-cloak class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700" type="submit">Crear mi tienda <span aria-hidden="true">→</span></button>
                    </div>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">¿Ya tienes una cuenta? <a class="font-bold text-blue-700 hover:text-blue-900" href="{{ url('/login') }}">Inicia sesión</a></p>
                <p class="mt-5 text-center text-[11px] text-slate-400">🔒 Registro seguro · Sin tarjeta de crédito · Tu catálogo queda aislado</p>
            </section>
        </div>
    </main>
    @if (config('services.turnstile.enabled'))<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
</x-layouts.app>
