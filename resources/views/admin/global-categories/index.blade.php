<x-layouts.app title="Categorías Globales | MiCatalogo Admin">
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <!-- Unified Admin Header with Breadcrumbs & Back Navigation -->
        <x-admin.header 
            :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Categorías globales']]" 
        />

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <!-- Page Header -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Categorías Globales</h1>
                    <p class="text-xs text-slate-500 mt-1">Organiza la taxonomía principal de productos y tiendas en toda la plataforma.</p>
                </div>

                <div class="flex items-center gap-3 text-xs font-semibold">
                    <span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700 border border-blue-100">
                        {{ $categories->whereNull('parent_id')->count() }} categorías raíz
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700 border border-slate-200">
                        {{ $categories->count() }} totales
                    </span>
                </div>
            </div>

            <!-- Flash Alerts -->
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
                    <p class="font-bold flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        No se pudo guardar la categoría:
                    </p>
                    <ul class="mt-2 list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <!-- Columna Izquierda: Formulario Crear Categoría -->
                <div class="lg:col-span-4">
                    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs sticky top-24">
                        <div class="border-b border-slate-100 pb-3">
                            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Nueva Categoría
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Agrega una categoría principal o subcategoría.</p>
                        </div>

                        <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.categories.store') }}">
                            @csrf
                            <input name="status" type="hidden" value="active">

                            <!-- Nombre -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700" for="cat-name">Nombre *</label>
                                <input 
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" 
                                    id="cat-name" 
                                    name="name" 
                                    type="text" 
                                    value="{{ old('name') }}" 
                                    placeholder="Ej: Calzado, Software, Ropa" 
                                    required 
                                    autofocus
                                >
                                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Slug personalizado (opcional) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700" for="cat-slug">
                                    Slug URL <span class="text-slate-400 font-normal">(opcional)</span>
                                </label>
                                <input 
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" 
                                    id="cat-slug" 
                                    name="slug" 
                                    type="text" 
                                    value="{{ old('slug') }}" 
                                    placeholder="ej: calzado-deportivo"
                                >
                                <p class="mt-1 text-[11px] text-slate-400">Si lo dejas vacío, se generará a partir del nombre.</p>
                                @error('slug') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Categoría Padre -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700" for="cat-parent">Jerarquía / Padre</label>
                                <select 
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" 
                                    id="cat-parent" 
                                    name="parent_id"
                                >
                                    <option value="">Ninguna (Categoría principal raíz)</option>
                                    @foreach ($categories->whereNull('parent_id') as $parent)
                                        <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>
                                            ↳ Subcategoría de: {{ $parent->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-slate-400">Máximo 1 nivel de anidamiento permitido.</p>
                                @error('parent_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Orden de visualización -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700" for="cat-sort">
                                    Orden <span class="text-slate-400 font-normal">(menor número = primero)</span>
                                </label>
                                <input 
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" 
                                    id="cat-sort" 
                                    name="sort_order" 
                                    type="number" 
                                    min="0" 
                                    value="{{ old('sort_order', 0) }}"
                                >
                                @error('sort_order') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition">
                                Guardar categoría
                            </button>
                        </form>
                    </section>
                </div>

                <!-- Columna Derecha: Árbol y Listado de Categorías -->
                <div class="lg:col-span-8">
                    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Estructura del Catálogo</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Categorías visibles en los filtros y vitrina pública.</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            @php
                                $rootCategories = $categories->whereNull('parent_id');
                            @endphp

                            @forelse ($rootCategories as $root)
                                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 transition hover:border-slate-300">
                                    <!-- Categoría Raíz -->
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700 font-bold text-xs">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                            </span>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h3 class="font-bold text-sm text-slate-900">{{ $root->name }}</h3>
                                                    <span class="rounded bg-slate-200/70 px-2 py-0.5 font-mono text-[10px] text-slate-600">
                                                        /{{ $root->slug }}
                                                    </span>
                                                    @if ($root->sort_order > 0)
                                                        <span class="text-[10px] text-slate-400">#{{ $root->sort_order }}</span>
                                                    @endif
                                                </div>
                                                <p class="text-[11px] text-slate-500 mt-0.5">
                                                    {{ $root->children->count() }} subcategorías · {{ $root->products_count ?? 0 }} productos asociados
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('admin.categories.destroy', $root) }}" onsubmit="return confirm('¿Estás seguro de eliminar la categoría principal {{ $root->name }}? Si tiene subcategorías, podrían quedar huérfanas.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Subcategorías anidadas -->
                                    @if ($root->children->isNotEmpty())
                                        <div class="mt-3 pl-6 sm:pl-10 space-y-2 border-l-2 border-blue-200/60 ml-4">
                                            @foreach ($root->children as $child)
                                                <div class="flex items-center justify-between rounded-lg bg-white p-2.5 border border-slate-200/80 shadow-2xs">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-slate-400 font-mono text-xs">↳</span>
                                                        <span class="text-xs font-bold text-slate-800">{{ $child->name }}</span>
                                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-500">
                                                            /{{ $child->slug }}
                                                        </span>
                                                        <span class="text-[10px] text-slate-400">
                                                            ({{ $child->products_count ?? 0 }} productos)
                                                        </span>
                                                    </div>

                                                    <form method="POST" action="{{ route('admin.categories.destroy', $child) }}" onsubmit="return confirm('¿Eliminar la subcategoría {{ $child->name }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 hover:underline">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-400">
                                    <svg class="mx-auto h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                    <p class="mt-2 text-xs font-semibold text-slate-500">No hay categorías registradas todavía.</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Usa el formulario lateral para agregar tu primera categoría principal.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</x-layouts.app>
