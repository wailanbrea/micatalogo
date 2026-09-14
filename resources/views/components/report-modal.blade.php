@props(['type', 'id', 'name'])

<div x-data="{ open: false }" class="inline-block">
    <button type="button" @click="open = true" class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-slate-600 transition">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span>Reportar contenido</span>
    </button>

    <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs" x-transition.opacity>
        <div @click.away="open = false" class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl" x-transition.scale>
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900">Reportar contenido</h3>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Estás reportando: <strong class="text-slate-700">{{ $name }}</strong>
            </p>

            <form action="{{ route('reports.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="id" value="{{ $id }}">

                <div>
                    <label for="reason-{{ $id }}" class="block text-xs font-bold text-slate-700">Motivo del reporte *</label>
                    <select id="reason-{{ $id }}" name="reason" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                        <option value="">Selecciona un motivo</option>
                        <option value="fraud">Fraude o estafa</option>
                        <option value="counterfeit">Producto falso o imitación</option>
                        <option value="prohibited_product">Producto prohibido o regulado</option>
                        <option value="illegal_content">Contenido ilegal</option>
                        <option value="adult_content">Contenido para adultos</option>
                        <option value="spam">Spam o información engañosa</option>
                        <option value="copyright">Infracción de derechos de autor</option>
                        <option value="other">Otro motivo</option>
                    </select>
                </div>

                <div>
                    <label for="desc-{{ $id }}" class="block text-xs font-bold text-slate-700">Detalles adicionales (opcional)</label>
                    <textarea id="desc-{{ $id }}" name="description" rows="3" maxlength="1000" placeholder="Explica brevemente la situación..." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600"></textarea>
                </div>

                @if (config('services.turnstile.enabled') && config('services.turnstile.site_key'))
                    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="report"></div>
                @endif

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-rose-700">
                        Enviar reporte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
