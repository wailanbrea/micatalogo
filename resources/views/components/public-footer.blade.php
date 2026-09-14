<footer id="site-footer" class="mt-16 overflow-hidden bg-[#0F172A] text-white">
    <div class="mx-auto max-w-[1400px] px-5 py-10 sm:px-8 lg:py-12">
        <div class="flex flex-col items-center justify-between gap-8 sm:flex-row">
            <a class="text-3xl font-black tracking-[-0.07em] text-white" href="{{ route('home') }}" aria-label="MiCatalogo, ir al inicio">Mi<span class="text-blue-400">Catalogo</span></a>
            <a class="inline-flex items-center gap-3 text-sm font-bold text-slate-300 transition hover:text-blue-400" href="https://www.instagram.com/bsolutions.dev?igsh=MWNhMm02Z2NzY2pvaA==" target="_blank" rel="noopener noreferrer" aria-label="Instagram de BSolutions">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-yellow-400 via-pink-500 to-purple-600 p-2 shadow-lg shadow-pink-500/20">
                    <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="5"></rect>
                        <circle cx="12" cy="12" r="4"></circle>
                        <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"></circle>
                    </svg>
                </span>
                Instagram
            </a>
            <a class="text-sm font-semibold text-slate-300 transition hover:text-blue-400" href="mailto:contacto@bsolutions.dev">contacto@bsolutions.dev</a>
        </div>

        <div class="mt-10 flex flex-col gap-3 border-t border-slate-700 pt-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} MiCatalogo. Todos los derechos reservados.</p>
            <p>Created by <a class="font-bold text-white underline decoration-blue-400 underline-offset-4 hover:text-blue-400" href="https://bsolutions.dev" target="_blank" rel="noopener noreferrer">BSolutions.dev</a></p>
        </div>
    </div>
</footer>
