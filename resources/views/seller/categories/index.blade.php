<x-layouts.app :title="'Categorías y unidades | '.$shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.summary', $shop)],
        ['label' => 'Categorías y unidades']
    ]" />
    @php
        $scopeLabels = ['shared' => 'Compartida', 'product' => 'Productos', 'service' => 'Servicios'];
        $isUnits = $tab === 'units';
        $formClass = 'grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2';
        $inputClass = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm';
    @endphp
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <x-seller.shop-header :shop="$shop" activeTab="categories" />
            <section class="space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                <div><h1 class="text-2xl font-bold">Categorías y unidades</h1>
                    <p class="mt-1 text-sm text-slate-600">Taxonomía sincronizada de {{ $shop->name }}. Desactivar conserva las referencias y el histórico.</p></div>
                <nav aria-label="Tipos de taxonomía" class="flex flex-wrap gap-2">
                    <a href="{{ route('seller.shops.categories.index', $shop) }}" @if (!$isUnits) aria-current="page" @endif class="rounded-lg px-4 py-3 font-semibold {{ !$isUnits ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700' }}">Categorías</a>
                    <a href="{{ route('seller.shops.categories.index', [$shop, 'tab' => 'units']) }}" @if ($isUnits) aria-current="page" @endif class="rounded-lg px-4 py-3 font-semibold {{ $isUnits ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700' }}">Unidades</a>
                </nav>
                @if (session('status'))<p role="status" class="rounded-lg bg-emerald-50 p-3 text-emerald-900">{{ session('status') }}</p>@endif
                @if ($errors->any())<div role="alert" class="rounded-lg bg-rose-50 p-3 text-rose-900"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form method="GET" action="{{ route('seller.shops.categories.index', $shop) }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="min-w-0 flex-1 text-sm font-semibold">Buscar por nombre<input class="{{ $inputClass }}" name="q" value="{{ request('q') }}" maxlength="120"></label>
                    <button class="rounded-lg bg-slate-800 px-4 py-3 text-white">Buscar</button>
                </form>
                @if ($createScopes !== [])
                    <details class="rounded-xl border border-blue-200 bg-blue-50 p-4" @if (old('record_id')) open @endif>
                        <summary class="cursor-pointer py-2 font-bold text-blue-900">{{ $isUnits ? 'Nueva unidad' : 'Nueva categoría' }}</summary>
                        <form method="POST" action="{{ $isUnits ? route('seller.shops.units.store', $shop) : route('seller.shops.categories.store', $shop) }}" class="{{ $formClass }} mt-3">
                            @csrf
                            <input type="hidden" name="record_id" value="{{ old('record_id', (string) Illuminate\Support\Str::uuid()) }}">
                            <input type="hidden" name="client_operation_uuid" value="{{ old('client_operation_uuid', (string) Illuminate\Support\Str::uuid()) }}">
                            <input type="hidden" name="expected_revision" value="">
                            <input type="hidden" name="status" value="active">
                            <label class="text-sm font-semibold">Nombre<input class="{{ $inputClass }}" name="name" value="{{ old('name') }}" required maxlength="{{ $isUnits ? 100 : 120 }}"></label>
                            @if ($isUnits)
                                <label class="text-sm font-semibold">Abreviatura<input class="{{ $inputClass }}" name="abbreviation" value="{{ old('abbreviation') }}" required maxlength="12"></label>
                            @else
                                <label class="text-sm font-semibold">Slug URL (opcional)<input class="{{ $inputClass }}" name="slug" value="{{ old('slug') }}" maxlength="120"></label>
                                <label class="text-sm font-semibold">Descripción<textarea class="{{ $inputClass }}" name="description" maxlength="2000">{{ old('description') }}</textarea></label>
                            @endif
                            <label class="text-sm font-semibold">Alcance<select class="{{ $inputClass }}" name="scope">@foreach ($createScopes as $scope)<option value="{{ $scope }}" @selected(old('scope', $defaultScope) === $scope)>{{ $scopeLabels[$scope] }}</option>@endforeach</select></label>
                            <button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white sm:col-span-2">Guardar {{ $isUnits ? 'unidad' : 'categoría' }}</button>
                        </form>
                    </details>
                @else
                    <p class="text-sm text-slate-600">Consulta: no tienes facultad para crear taxonomía en esta tienda.</p>
                @endif
                <div class="space-y-3">
                    @forelse ($isUnits ? $units : $categories as $item)
                        @php
                            $record = $isUnits ? $item : $taxonomyAccess->categoryRecord($item);
                            $payload = $record?->payload ?? [];
                            $scope = $isUnits ? ($payload['scope'] ?? 'shared') : $taxonomyAccess->categoryScope($item);
                            $name = $isUnits ? $payload['name'] : $item->name;
                            $active = $isUnits ? ($payload['is_active'] && !$payload['is_deleted']) : $item->status->value === 'active';
                            $canEdit = $isUnits ? $taxonomyAccess->canModifyUnit($shop, auth()->user(), $record->record_uuid) : $taxonomyAccess->canModifyCategory($item, auth()->user());
                            $target = $isUnits ? $record->record_uuid : $item;
                            $update = route($isUnits ? 'seller.shops.units.update' : 'seller.shops.categories.update', [$shop, $target]);
                            $destroy = route($isUnits ? 'seller.shops.units.destroy' : 'seller.shops.categories.destroy', [$shop, $target]);
                        @endphp
                        <article class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="break-words font-bold">{{ $name }}</h2><p class="text-sm text-slate-600">{{ $scopeLabels[$scope] ?? 'Alcance no válido' }} · {{ $active ? 'Activa' : 'Inactiva' }}{{ $isUnits ? ' · '.$payload['abbreviation'] : '' }}</p></div>
                                @if (!$canEdit)<span class="text-sm text-slate-500">Sólo consulta</span>@endif
                            </div>
                            @if ($canEdit)
                                <details class="mt-3"><summary class="cursor-pointer py-2 text-sm font-semibold text-blue-800">Editar {{ $isUnits ? 'unidad' : 'categoría' }} {{ $name }}</summary>
                                    <form method="POST" action="{{ $update }}" class="{{ $formClass }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="client_operation_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="expected_revision" value="{{ $payload['revision'] ?? '' }}">
                                        <input type="hidden" name="scope" value="{{ $scope }}">
                                        <label class="text-sm font-semibold">Nombre<input class="{{ $inputClass }}" name="name" value="{{ $name }}" required maxlength="{{ $isUnits ? 100 : 120 }}"></label>
                                        @if ($isUnits)<label class="text-sm font-semibold">Abreviatura<input class="{{ $inputClass }}" name="abbreviation" value="{{ $payload['abbreviation'] }}" required maxlength="12"></label>
                                        @else
                                            <label class="text-sm font-semibold">Slug URL<input class="{{ $inputClass }}" name="slug" value="{{ $item->slug }}" maxlength="120"></label>
                                            <label class="text-sm font-semibold">Descripción<textarea class="{{ $inputClass }}" name="description" maxlength="2000">{{ $payload['description'] ?? '' }}</textarea></label>
                                            <label class="text-sm font-semibold">Orden<input class="{{ $inputClass }}" name="sort_order" type="number" min="0" max="65535" value="{{ $item->sort_order }}"></label>
                                        @endif
                                        <label class="text-sm font-semibold">Estado<select class="{{ $inputClass }}" name="status"><option value="active" @selected($active)>Activa</option><option value="inactive" @selected(!$active)>Inactiva</option></select></label>
                                        <button class="rounded-lg bg-blue-700 px-4 py-3 text-white sm:col-span-2">Guardar cambios</button>
                                    </form>
                                </details>
                                @if ($active)<form method="POST" action="{{ $destroy }}" class="mt-2">@csrf @method('DELETE')<input type="hidden" name="client_operation_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_revision" value="{{ $payload['revision'] ?? '' }}"><button class="min-h-11 rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-900">Desactivar {{ $isUnits ? 'unidad' : 'categoría' }}</button></form>@endif
                            @endif
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 p-6 text-slate-600">No hay {{ $isUnits ? 'unidades' : 'categorías' }} en este filtro y alcance.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</x-layouts.app>
