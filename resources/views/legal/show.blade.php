<x-layouts.app :title="$title.' | MiCatalogo'">
    <main class="min-h-screen bg-slate-50 px-4 py-10 text-slate-900 sm:px-6 lg:px-8">
        <article class="mx-auto max-w-4xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <a href="{{ route('home') }}" class="text-sm font-bold text-blue-700 hover:underline">← Volver a MiCatalogo</a>
            <p class="mt-10 text-xs font-black uppercase tracking-[.2em] text-blue-600">{{ $eyebrow }}</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">{{ $title }}</h1>
            <p class="mt-3 text-sm text-slate-500">Última actualización: {{ now()->format('d/m/Y') }}</p>
            <div class="mt-8 space-y-6">
                @foreach ($sections as $section)
                    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                        <h2 class="text-lg font-black text-slate-950">{{ $section['title'] }}</h2>
                        <p class="mt-2 text-sm leading-7 text-slate-600">{{ $section['body'] }}</p>
                    </section>
                @endforeach
            </div>
            <a href="{{ route('support.create') }}" class="mt-8 inline-flex rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-black text-white hover:bg-blue-700">Contactar soporte</a>
        </article>
    </main>
</x-layouts.app>
