@props([
    'position' => 'home_after_grid_1',
    'class' => '',
])

@if (config('catalog.ads.enabled', false))
    <aside aria-label="Espacio publicitario" class="my-6 w-full overflow-hidden rounded-xl border border-dashed border-slate-300 bg-white/60 p-4 text-center text-xs text-slate-400 {{ $class }}">
        <div class="mb-1 uppercase tracking-widest text-[10px] font-semibold text-slate-400">Publicidad</div>
        <div class="flex min-h-[90px] items-center justify-center rounded-lg bg-slate-50 text-slate-400">
            <span>Espacio reservado ({{ $position }})</span>
        </div>
    </aside>
@endif
