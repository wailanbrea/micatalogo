<x-layouts.app title="Descarga MiCatalogo | App Android">
    @php
        $release = $androidReleases[0] ?? null;
        $downloadUrl = $release ? route('downloads.android', $release['version_code']) : '#';
    @endphp

    <div class="min-h-screen overflow-hidden bg-[#f8fbff] font-sans text-[#14213d] selection:bg-blue-600 selection:text-white">
        <div class="pointer-events-none fixed inset-0 -z-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-48 -top-48 h-[38rem] w-[38rem] rounded-full bg-blue-100/70 blur-[100px]"></div>
            <div class="absolute -right-64 top-20 h-[48rem] w-[48rem] rounded-full bg-sky-100/75 blur-[110px]"></div>
            <div class="absolute bottom-0 right-[-12rem] h-[34rem] w-[34rem] rounded-full bg-blue-200/60 blur-[100px]"></div>
        </div>

        <header class="relative z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl">
            <div class="mx-auto flex h-[78px] max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="group flex items-center gap-3" aria-label="Volver al inicio de MiCatalogo">
                    <span class="flex h-11 w-11 items-center justify-center rounded-[14px] bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-lg shadow-blue-600/25 transition group-hover:-rotate-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 8.5h14v10.25A2.25 2.25 0 0 1 16.75 21h-9.5A2.25 2.25 0 0 1 5 18.75V8.5Zm3-1.25V6a4 4 0 0 1 8 0v1.25M12 12v4m0 0 2-2m-2 2-2-2"/>
                        </svg>
                    </span>
                    <span class="text-[27px] font-extrabold leading-none tracking-[-0.055em] text-[#14213d]">Mi<span class="text-blue-600">Catalogo</span></span>
                </a>

                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-4 py-2.5 text-sm font-bold text-slate-600 shadow-sm transition hover:border-blue-200 hover:text-blue-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Volver al inicio
                </a>
            </div>
        </header>

        <main class="relative z-10">
            <section class="mx-auto max-w-7xl px-5 pb-10 pt-12 sm:px-8 sm:pt-16 lg:px-10 lg:pb-16 lg:pt-20">
                <div class="grid items-center gap-14 lg:grid-cols-[minmax(0,1fr)_minmax(430px,0.88fr)] lg:gap-8">
                    <div class="max-w-[680px]">
                        <div class="flex items-center gap-3 text-sm font-bold text-blue-700">
                            <span class="h-2.5 w-2.5 rounded-full bg-blue-600 shadow-[0_0_0_6px_rgba(37,99,235,0.12)]"></span>
                            Aplicación oficial para Android
                        </div>
                        <h1 class="mt-6 max-w-[700px] text-[clamp(3rem,6vw,5.25rem)] font-extrabold leading-[0.98] tracking-[-0.065em] text-[#14213d]">
                            Descarga la app APK<br>
                            de <span class="bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-500 bg-clip-text text-transparent">MiCatalogo</span>
                        </h1>
                        <p class="mt-7 max-w-[620px] text-lg leading-8 text-slate-600 sm:text-xl">
                            Gestiona tus productos, inventario, ventas y comparte tu catálogo de forma más rápida y desde cualquier lugar.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @if ($release)
                                <a href="{{ $downloadUrl }}" class="group inline-flex items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-blue-600 to-blue-500 px-7 py-4 text-base font-extrabold text-white shadow-xl shadow-blue-600/25 transition hover:-translate-y-0.5 hover:from-blue-700 hover:to-blue-600">
                                    <svg class="h-6 w-6 transition group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
                                    Descargar APK
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center gap-3 rounded-2xl bg-slate-300 px-7 py-4 text-base font-extrabold text-slate-600">APK no disponible</span>
                            @endif
                            <a href="#instrucciones" class="inline-flex items-center justify-center gap-3 rounded-2xl border-2 border-blue-100 bg-white/80 px-7 py-4 text-base font-extrabold text-blue-800 shadow-sm transition hover:border-blue-300 hover:bg-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h6m-6 4h6m-6 4h3m-6 7h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.5a2 2 0 0 0-2-2h-3a2 2 0 0 0-2 2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
                                Ver instrucciones
                            </a>
                        </div>

                        <div class="mt-9 grid max-w-[700px] grid-cols-3 divide-x divide-slate-200 rounded-2xl border border-slate-200/80 bg-white/85 px-2 py-4 shadow-[0_12px_30px_rgba(44,81,140,0.08)] sm:px-5 sm:py-5">
                            <div class="flex items-center gap-3 px-2 sm:px-4">
                                <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 sm:flex"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z"/></svg></span>
                                <div><p class="text-xs text-slate-500">Versión</p><p class="mt-0.5 text-base font-extrabold text-[#14213d]">{{ $release['version_name'] ?? 'Próximamente' }}</p></div>
                            </div>
                            <div class="flex items-center gap-3 px-2 sm:px-4">
                                <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 sm:flex"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.523 15.341a1 1 0 0 0 1.732-1l-.001-.002-1.732-3a1 1 0 1 0-1.732 1l1.733 3.002ZM6.477 15.34l1.732-3a1 1 0 1 0-1.732-1l-1.733 3.002a1 1 0 0 0 1.733.998ZM12 5c-3.314 0-6 2.239-6 5h12c0-2.761-2.686-5-6-5Zm-3.5 3A1.5 1.5 0 1 1 10 6.5 1.5 1.5 0 0 1 8.5 8Zm7 0A1.5 1.5 0 1 1 17 6.5 1.5 1.5 0 0 1 15.5 8ZM12 11c-3.866 0-7 2.239-7 5h14c0-2.761-3.134-5-7-5Z"/></svg></span>
                                <div><p class="text-xs text-slate-500">Android</p><p class="mt-0.5 text-base font-extrabold text-[#14213d]">8+</p></div>
                            </div>
                            <div class="flex items-center gap-3 px-2 sm:px-4">
                                <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 sm:flex"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/></svg></span>
                                <div><p class="text-xs text-slate-500">Instalación</p><p class="mt-0.5 text-base font-extrabold text-[#14213d]">Rápida</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="relative min-h-[520px] lg:min-h-[600px]">
                        <div class="absolute left-1/2 top-1/2 h-[480px] w-[480px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-br from-blue-100/90 via-white/30 to-sky-200/70 blur-2xl" aria-hidden="true"></div>
                        <div class="absolute left-1/2 top-1/2 h-[430px] w-[430px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-blue-100/80" aria-hidden="true"></div>

                        <div class="absolute left-0 top-[13%] z-20 hidden w-40 -rotate-3 rounded-2xl border border-blue-100 bg-white/95 p-4 shadow-xl shadow-blue-900/10 sm:block lg:left-0">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 7h6m-6 4h3"/></svg></span>
                            <p class="mt-3 text-sm font-extrabold leading-5 text-[#14213d]">Gestiona<br>productos</p>
                        </div>
                        <div class="absolute bottom-[16%] left-1 z-20 hidden w-40 rotate-2 rounded-2xl border border-blue-100 bg-white/95 p-4 shadow-xl shadow-blue-900/10 sm:block lg:left-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg></span>
                            <p class="mt-3 text-sm font-extrabold leading-5 text-[#14213d]">Controla tu<br>inventario</p>
                        </div>
                        <div class="absolute right-0 top-[39%] z-20 hidden w-40 rotate-3 rounded-2xl border border-blue-100 bg-white/95 p-4 shadow-xl shadow-blue-900/10 sm:block lg:right-[-3%]">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2l2.4 11.5a2 2 0 0 0 2 1.5h7.2a2 2 0 0 0 1.9-1.4L21 8H7m3 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg></span>
                            <p class="mt-3 text-sm font-extrabold leading-5 text-[#14213d]">Registra<br>tus ventas</p>
                        </div>

                        <div class="relative z-10 mx-auto w-[260px] rotate-[4deg] rounded-[3.2rem] border-[9px] border-slate-900 bg-slate-900 p-2 shadow-[22px_30px_45px_rgba(21,54,105,0.28)] sm:w-[290px] lg:w-[305px]">
                            <div class="absolute left-1/2 top-2 z-20 h-5 w-24 -translate-x-1/2 rounded-full bg-slate-900"></div>
                            <div class="overflow-hidden rounded-[2.45rem] bg-white">
                                <div class="flex items-center justify-between bg-blue-700 px-5 pb-2 pt-5 text-[9px] font-bold text-white"><span>9:41</span><span class="flex items-center gap-1"><span class="h-2 w-3 rounded-sm border border-white"></span><span class="h-2 w-2 rounded-full bg-white"></span></span></div>
                                <div class="flex items-center justify-between bg-blue-700 px-4 pb-4 pt-1 text-white"><div class="flex items-center gap-2"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg><span class="text-sm font-extrabold">MiCatalogo</span></div><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m3 4a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"/></svg></div>
                                <div class="space-y-3 bg-slate-50 p-3">
                                    <div class="rounded-2xl bg-white p-3 shadow-sm"><p class="text-[9px] font-bold text-slate-500">¡Hola, María!</p><p class="mt-1 text-[8px] text-slate-400">Aquí está el resumen de tu negocio.</p><div class="mt-3 grid grid-cols-3 gap-1.5"><div class="rounded-xl bg-blue-50 p-2 text-center"><p class="text-base font-black text-slate-900">24</p><p class="text-[7px] text-slate-500">Productos</p></div><div class="rounded-xl bg-emerald-50 p-2 text-center"><p class="text-base font-black text-slate-900">18</p><p class="text-[7px] text-slate-500">Inventario</p></div><div class="rounded-xl bg-violet-50 p-2 text-center"><p class="text-base font-black text-slate-900">12</p><p class="text-[7px] text-slate-500">Ventas</p></div></div></div>
                                    <div class="rounded-2xl bg-white p-3 shadow-sm"><div class="flex items-center justify-between"><p class="text-[10px] font-extrabold text-slate-800">Productos recientes</p><span class="text-[8px] font-bold text-blue-600">Ver todos</span></div><div class="mt-3 grid grid-cols-3 gap-1.5"><div><div class="h-16 rounded-xl bg-gradient-to-br from-orange-100 to-orange-300"></div><p class="mt-1 truncate text-[7px] font-bold text-slate-700">Termo deportivo</p><p class="text-[8px] font-black text-slate-900">S/ 45.00</p></div><div><div class="h-16 rounded-xl bg-gradient-to-br from-slate-100 to-slate-300"></div><p class="mt-1 truncate text-[7px] font-bold text-slate-700">Zapatillas urbanas</p><p class="text-[8px] font-black text-slate-900">S/ 120.00</p></div><div><div class="h-16 rounded-xl bg-gradient-to-br from-slate-200 to-slate-500"></div><p class="mt-1 truncate text-[7px] font-bold text-slate-700">Gorra clásica</p><p class="text-[8px] font-black text-slate-900">S/ 35.00</p></div></div></div>
                                    <div class="flex items-center gap-2 rounded-xl bg-blue-50 p-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h5m-9 8 3-3h9a4 4 0 0 0 4-4V7a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v6a4 4 0 0 0 4 4v3Z"/></svg></span><div><p class="text-[9px] font-extrabold text-slate-800">Comparte tu catálogo</p><p class="text-[7px] text-slate-500">Envíalo por WhatsApp</p></div></div>
                                </div>
                                <div class="grid grid-cols-4 border-t border-slate-100 bg-white px-2 py-3 text-center text-[7px] font-bold text-slate-400"><span class="text-blue-600">⌂<br>Inicio</span><span>□<br>Productos</span><span>▥<br>Ventas</span><span>•••<br>Más</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="instrucciones" class="mx-auto max-w-7xl px-5 pb-14 sm:px-8 lg:px-10 lg:pb-20">
                <div class="grid gap-5 lg:grid-cols-[1fr_1fr]">
                    <div class="flex items-center gap-5 rounded-3xl border border-blue-100 bg-blue-50/75 p-5 sm:p-6">
                        <div class="h-28 w-28 shrink-0 rounded-2xl bg-white p-2 shadow-sm sm:h-32 sm:w-32">{!! $qrSvg !!}</div>
                        <div><p class="text-sm font-extrabold text-[#14213d] sm:text-base">También puedes escanear este código</p><p class="mt-1 text-sm leading-6 text-slate-600">para descargar la app APK desde tu celular Android.</p></div>
                    </div>
                    <div class="flex items-center gap-5 rounded-3xl border border-amber-200 bg-amber-50/80 p-5 sm:p-6">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-400 text-white shadow-sm"><svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.3 3.8 2.7 17a2 2 0 0 0 1.74 3h15.12a2 2 0 0 0 1.74-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg></span>
                        <div><p class="text-sm font-extrabold text-[#14213d] sm:text-base">Si tu navegador bloquea la instalación</p><p class="mt-1 text-sm leading-6 text-slate-600">activa “Permitir apps de este origen” en la configuración de seguridad de Android.</p></div>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap justify-center gap-3 sm:justify-start">
                    @foreach ([['Gratis', 'M'], ['Actualización manual', '↻'], ['Ligera', '◆'], ['Para vendedores', '●']] as [$label, $icon])
                        <span class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-white px-5 py-2.5 text-sm font-bold text-slate-600 shadow-sm"><span class="text-base font-black text-blue-600">{{ $icon }}</span>{{ $label }}</span>
                    @endforeach
                </div>
            </section>

            <section class="border-t border-slate-200/70 bg-white/75 px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
                <div class="mx-auto max-w-5xl">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><p class="text-xs font-black uppercase tracking-[0.2em] text-blue-600">Versiones oficiales</p><h2 class="mt-2 text-3xl font-extrabold tracking-tight text-[#14213d] sm:text-4xl">Historial de descargas</h2></div><p class="text-sm font-semibold text-slate-500">{{ number_format($downloadCount) }} descargas registradas</p></div>
                    <div class="mt-8 space-y-4">
                        @forelse ($androidReleases as $release)
                            <article class="flex flex-col gap-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_14px_35px_rgba(36,76,135,0.08)] sm:flex-row sm:items-center sm:justify-between sm:p-7">
                                <div class="flex items-start gap-4"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg></span><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-lg font-extrabold text-[#14213d]">MiCatalogo {{ $release['version_name'] }}</h3><span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">Recomendada</span></div><p class="mt-1 text-xs font-semibold text-slate-500">Publicada el {{ \Illuminate\Support\Carbon::parse($release['release_date'])->locale('es')->translatedFormat('d \d\e F \d\e Y') }} · Código {{ $release['version_code'] }}</p><p class="mt-3 text-sm leading-6 text-slate-600">{{ $release['release_notes'] }}</p></div></div>
                                <a href="{{ route('downloads.android', $release['version_code']) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700">Descargar APK <span aria-hidden="true">↗</span></a>
                            </article>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Aún no hay versiones Android publicadas.</div>
                        @endforelse
                    </div>
                    <div class="mt-8 flex items-center justify-center gap-4 rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100"><svg class="h-7 w-7" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M7 6.8c-.6.5-1 1.4-1 2.6v29.2c0 1.2.4 2.1 1 2.6L28.2 24 7 6.8Z" fill="#34A853"/><path d="m7 6.8 26.7 15.4L28.2 24 7 6.8Z" fill="#FBBC04"/><path d="M7 41.2 33.7 25.8 28.2 24 7 41.2Z" fill="#EA4335"/><path d="m33.7 25.8 6.1-3.5c1.6-.9 1.6-2.2 0-3.1L33.7 15.7 28.2 24l5.5 1.8Z" fill="#4285F4"/></svg></span><div class="text-left"><h2 class="text-base font-extrabold text-[#14213d]">Próximamente en Play Store</h2><p class="mt-1 text-sm text-slate-500">Estamos preparando la publicación oficial para instalar MiCatalogo desde Google Play.</p></div></div>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-slate-200 bg-white/70 py-8 text-center text-xs font-semibold text-slate-500"><p>© {{ now()->year }} MiCatalogo · Descargas oficiales</p></footer>
    </div>
</x-layouts.app>
