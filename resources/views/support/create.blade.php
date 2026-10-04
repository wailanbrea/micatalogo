<x-layouts.app title="Soporte | MiCatalogo">
    <main class="min-h-screen bg-slate-50 px-4 py-10 sm:px-6">
        <div class="mx-auto max-w-2xl">
            <a href="{{ route('home') }}" class="text-sm font-bold text-blue-700 hover:underline">MiCatalogo</a>
            <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <h1 class="text-2xl font-black text-slate-900">Soporte y sugerencias</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Cuéntanos el problema o la mejora que necesitas. Si ya iniciaste sesión, vincularemos tu solicitud con tu cuenta y catálogo.</p>

                @if (session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                <form method="POST" action="{{ route('support.store') }}" class="mt-6 space-y-5">@csrf
                    <div><label class="text-sm font-bold text-slate-700">Tipo de solicitud</label><select name="category" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="problem" @selected(old('category') === 'problem')>Tengo un problema</option><option value="suggestion" @selected(old('category') === 'suggestion')>Quiero sugerir una mejora</option></select></div>
                    @guest
                        <div><label class="text-sm font-bold text-slate-700">Nombre</label><input name="name" value="{{ old('name') }}" maxlength="120" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                        <div><label class="text-sm font-bold text-slate-700">Correo para responderte</label><input name="email" type="email" required value="{{ old('email') }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                    @else
                        <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Enviarás esta solicitud como <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).</div>
                        <input name="email" type="hidden" value="{{ auth()->user()->email }}">
                    @endguest
                    <div><label class="text-sm font-bold text-slate-700">Asunto</label><input name="subject" required maxlength="160" value="{{ old('subject') }}" placeholder="Describe brevemente tu consulta" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                    <div><label class="text-sm font-bold text-slate-700">Mensaje</label><textarea name="message" required rows="7" maxlength="4000" placeholder="Incluye los pasos, mensaje de error o tu sugerencia." class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('message') }}</textarea></div>
                    @if (config('services.turnstile.enabled') && config('services.turnstile.site_key'))
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="support"></div>
                    @endif
                    <button class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700">Enviar a soporte</button>
                </form>
            </section>
        </div>
    </main>
</x-layouts.app>

@if (config('services.turnstile.enabled') && config('services.turnstile.site_key'))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
