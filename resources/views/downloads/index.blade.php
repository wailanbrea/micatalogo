<x-layouts.app title="Descargas | MiCatalogo">
    <div class="min-h-screen bg-slate-950 text-white">
        <header class="border-b border-white/10 bg-slate-950/95">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-black tracking-tight">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm shadow-lg shadow-blue-600/30">M</span>
                    <span>Mi<span class="text-blue-400">Catalogo</span></span>
                </a>
                <a href="{{ route('home') }}" class="rounded-lg border border-white/15 px-3 py-2 text-xs font-bold text-slate-300 transition hover:border-white/30 hover:text-white">Volver al inicio</a>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden border-b border-white/10">
                <div class="pointer-events-none absolute -left-40 -top-40 h-96 w-96 rounded-full bg-blue-600/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-48 -right-20 h-[30rem] w-[30rem] rounded-full bg-indigo-500/20 blur-3xl"></div>
                <div class="relative mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[1fr_380px] lg:items-center lg:px-8 lg:py-28">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-blue-400/25 bg-blue-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.18em] text-blue-300">
                            Centro de descargas
                        </span>
                        <h1 class="mt-6 max-w-3xl text-4xl font-black tracking-tight text-white sm:text-6xl">Todas tus actualizaciones, en un solo lugar.</h1>
                        <p class="mt-6 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">Descarga la aplicación Android de MiCatalogo, revisa las novedades de cada versión y mantén tu operación al día.</p>
                        <div class="mt-8 flex flex-wrap items-center gap-3 text-sm text-slate-400">
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">APK verificada</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">Actualizaciones seguras</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">{{ number_format($downloadCount) }} descargas</span>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur sm:p-6">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Estado del servicio</span>
                            <span class="flex items-center gap-2 text-xs font-bold text-emerald-300"><span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_12px_#34d399]"></span>Activo</span>
                        </div>
                        <div class="mt-8 flex items-center gap-4">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-black shadow-lg shadow-blue-600/30">M</div>
                            <div>
                                <p class="text-lg font-black text-white">MiCatalogo para Android</p>
                                <p class="mt-1 text-sm text-slate-400">Herramientas para administrar tu catálogo</p>
                            </div>
                        </div>
                        <div class="mt-8 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4"><p class="text-2xl font-black text-white">{{ count($androidReleases) }}</p><p class="mt-1 text-xs text-slate-400">Versiones disponibles</p></div>
                            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4"><p class="text-2xl font-black text-white">{{ number_format($downloadCount) }}</p><p class="mt-1 text-xs text-slate-400">Descargas Android</p></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-slate-50 py-16 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-2">
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Historial de versiones</p>
                        <h2 class="text-3xl font-black tracking-tight sm:text-4xl">Actualizaciones disponibles</h2>
                        <p class="max-w-2xl text-sm leading-6 text-slate-500">Cada APK se valida antes de publicarse. Instala siempre la versión más reciente para mantener la compatibilidad con MiCatalogo.</p>
                    </div>

                    <div class="mt-10 space-y-4">
                        @forelse ($androidReleases as $release)
                            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg sm:p-7">
                                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-700"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v-2M3 17v2a2 2 0 0 0 2 2"/></svg></div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-lg font-black text-slate-900">MiCatalogo {{ $release['version_name'] }}</h3>
                                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">Recomendada</span>
                                            </div>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">Publicada el {{ \Illuminate\Support\Carbon::parse($release['release_date'])->locale('es')->translatedFormat('d \\d\\e F \\d\\e Y') }} · Código {{ $release['version_code'] }}</p>
                                            @if ($release['release_notes'])
                                                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ $release['release_notes'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('downloads.android', $release['version_code']) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700">Descargar APK <span aria-hidden="true">↗</span></a>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Aún no hay versiones Android publicadas.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="bg-white py-16 text-slate-900 sm:py-20">
                <div class="mx-auto flex max-w-3xl flex-col items-center px-4 text-center sm:px-6 lg:px-8">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100" aria-hidden="true">
                        <svg class="h-9 w-9" viewBox="0 0 48 48" fill="none"><path d="M7 6.8c-.6.5-1 1.4-1 2.6v29.2c0 1.2.4 2.1 1 2.6L28.2 24 7 6.8Z" fill="#34A853"/><path d="m7 6.8 26.7 15.4L28.2 24 7 6.8Z" fill="#FBBC04"/><path d="M7 41.2 33.7 25.8 28.2 24 7 41.2Z" fill="#EA4335"/><path d="m33.7 25.8 6.1-3.5c1.6-.9 1.6-2.2 0-3.1L33.7 15.7 28.2 24l5.5 1.8Z" fill="#4285F4"/></svg>
                    </div>
                    <h2 class="mt-5 text-2xl font-black tracking-tight sm:text-3xl">Próximamente en Play Store</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Estamos preparando la publicación oficial para que puedas instalar MiCatalogo desde Google Play.</p>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 bg-slate-950 py-8 text-center text-xs text-slate-500">
            <p>© {{ now()->year }} MiCatalogo · Descargas oficiales</p>
        </footer>
    </div>
</x-layouts.app>
