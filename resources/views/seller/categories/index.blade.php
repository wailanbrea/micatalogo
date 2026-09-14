<x-layouts.app :title="'Categorías | ' . $shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Categorías']
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <!-- Shop Context & Local Navigation Tabs -->
            <x-seller.shop-header :shop="$shop" activeTab="categories" />

            <!-- Main Card -->
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <div class="border-b border-slate-100 pb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">Categorías internas de la tienda</h1>
                            <p class="text-xs text-slate-500 mt-1">Organiza tus productos en secciones personalizadas para que tus clientes encuentren rápido lo que buscan.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                            {{ $shop->name }}
                        </span>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-xs font-bold text-emerald-800 border border-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                @error('name')
                    <div class="mt-4 rounded-lg bg-rose-50 p-3 text-xs font-bold text-rose-800 border border-rose-200">
                        {{ $message }}
                    </div>
                @enderror

                <!-- Create Category Form -->
                <form class="mt-6 grid gap-3 sm:grid-cols-12" method="POST" action="{{ route('seller.shops.categories.store', $shop) }}">
                    @csrf
                    <input name="status" type="hidden" value="active">
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-bold text-slate-700 mb-1" for="name">Nombre de categoría *</label>
                        <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="name" name="name" placeholder="Ej: Promociones, Calzado, Bebidas" required>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1" for="slug">Slug URL <span class="text-slate-400 font-normal">(opcional)</span></label>
                        <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" id="slug" name="slug" placeholder="promociones">
                    </div>
                    <div class="sm:col-span-3 flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition">
                            + Agregar categoría
                        </button>
                    </div>
                </form>

                <!-- Categories List -->
                <div class="mt-8 border-t border-slate-100 pt-6">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Categorías creadas ({{ $categories->count() }})</h2>

                    @forelse ($categories as $category)
                        <article class="mt-2.5 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/50 px-4 py-3 hover:border-slate-300 transition">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-slate-800">{{ $category->name }}</span>
                                <span class="rounded bg-slate-200/70 px-2 py-0.5 font-mono text-[10px] text-slate-600">
                                    /{{ $category->slug }}
                                </span>
                            </div>

                            <form method="POST" action="{{ route('seller.shops.categories.destroy', [$shop, $category]) }}" onsubmit="return confirm('¿Deseas eliminar la categoría {{ $category->name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:underline transition">
                                    Eliminar
                                </button>
                            </form>
                        </article>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-slate-400 text-xs">
                            No tienes categorías internas creadas aún. Agrega una arriba para clasificar los productos de tu tienda.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</x-layouts.app>
