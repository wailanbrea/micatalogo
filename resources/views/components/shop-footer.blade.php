@props(['shop'])

<footer class="mt-16 border-t border-slate-200 bg-white text-slate-600">
    <div class="mx-auto max-w-[1400px] px-4 py-8 sm:px-8">
        <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
            <div class="flex flex-col items-center sm:items-start gap-1">
                <p class="text-sm font-bold text-slate-900">{{ $shop->name }}</p>
                <p class="text-xs text-slate-500">Catálogo digital exclusivo con atención directa por WhatsApp.</p>
            </div>
            
            <div class="flex items-center gap-4 text-xs font-semibold">
                <a class="flex items-center gap-1.5 text-emerald-700 hover:text-emerald-800 transition" href="{{ route('track.wa.shop', $shop) }}" target="_blank" rel="noopener noreferrer">
                    <svg class="h-4 w-4 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    <span>Contactar por WhatsApp</span>
                </a>
                @if ($shop->instagram)
                    <a class="flex items-center gap-1.5 text-slate-600 hover:text-pink-600 transition" href="https://instagram.com/{{ ltrim($shop->instagram, '@') }}" target="_blank" rel="noopener noreferrer">
                        <span>Instagram</span>
                    </a>
                @endif
                <x-report-modal type="shop" :id="$shop->public_id" :name="$shop->name" />
            </div>
        </div>

        <div class="mt-6 flex flex-col items-center justify-between gap-2 border-t border-slate-100 pt-4 text-[11px] text-slate-400 sm:flex-row">
            <p>© {{ now()->year }} {{ $shop->name }}. Todos los derechos reservados.</p>
            <p class="flex items-center gap-1">
                <span>Powered by</span>
                <a class="font-bold text-slate-600 hover:text-blue-600 transition" href="{{ route('home') }}">
                    MiCatalogo
                </a>
            </p>
        </div>
    </div>
</footer>
