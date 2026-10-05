<x-layouts.app :title="'Importar inventario | '.$shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
        ['label' => 'Importar inventario']
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <x-seller.shop-header :shop="$shop" activeTab="products" />

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">Importa tu inventario</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600">Sube tu archivo CSV o Excel. MiCatalogo intentará reconocer automáticamente las columnas y te mostrará una vista previa antes de guardar cualquier producto.</p>
                    </div>
                    <a href="{{ route('seller.shops.products.index', $shop) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Volver a productos</a>
                </div>

                @if ($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <!-- Format explanation banner -->
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-xs text-slate-700">
                    <p class="font-bold text-slate-900 mb-1.5">Formatos y encabezados reconocidos automáticamente:</p>
                    <div class="flex flex-wrap gap-1.5 font-mono text-[11px]">
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">nombre / producto</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">sku / código</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">barcode</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">marca</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">categoría</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">descripción</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">precio</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">costo</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">stock</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">notas</span>
                        <span class="rounded bg-white px-2 py-0.5 border border-slate-200 font-semibold text-slate-800">atributos</span>
                    </div>
                    <p class="mt-2 text-slate-500">Si tu archivo tiene una estructura poco común, puedes adaptarlo antes de subirlo o indicar qué columna corresponde a cada dato en el selector.</p>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_280px]">
                    <form method="POST" action="{{ route('seller.shops.products.import.preview', $shop) }}" enctype="multipart/form-data" class="rounded-xl border border-dashed border-indigo-300 bg-indigo-50/40 p-6">
                        @csrf
                        <label for="inventory-file" class="block text-sm font-bold text-slate-900">Archivo de inventario</label>
                        <p class="mt-1 text-xs text-slate-500">Formatos soportados: CSV, TXT o Excel (.xlsx). Tamaño máximo 10MB.</p>
                        <input id="inventory-file" name="file" type="file" accept=".csv,.txt,.xlsx" required class="mt-4 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                        
                        @if ($headers !== [])
                            <div class="mt-5 border-t border-indigo-200 pt-4">
                                <p class="text-sm font-black text-slate-900">Relaciona las columnas del archivo</p>
                                <p class="mt-1 text-xs text-slate-600">Este formato se recordará para futuras importaciones de esta tienda.</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @foreach ($fields as $field => $label)
                                        <label class="block text-xs font-bold text-slate-700">{{ $label }}@if (in_array($field, ['name', 'price'], true)) <span class="text-rose-600">*</span>@endif
                                            <select name="mapping[{{ $field }}]" class="mt-1 block w-full rounded-lg border-slate-200 text-sm">
                                                <option value="">No importar</option>
                                                @foreach ($headers as $header)
                                                    <option value="{{ $header }}" @selected(($mapping[$field] ?? '') === $header)>{{ $header }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <button type="submit" class="mt-5 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-indigo-700 transition">Previsualizar archivo</button>
                    </form>

                    <aside class="space-y-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                            <p class="font-black uppercase tracking-wider text-slate-800">Cupo de productos</p>
                            <p class="mt-2 text-slate-700">Esta tienda tiene <strong>{{ $quota['product_count'] }}</strong> de <strong>{{ $quota['product_limit'] }}</strong> productos permitidos por su plan (<strong>{{ $quota['plan_label'] }}</strong>).</p>
                            <p class="mt-1 text-slate-700">Cupo disponible para nuevos productos: <strong class="text-indigo-700">{{ $quota['products_remaining'] }}</strong>.</p>
                            <p class="mt-2 text-[11px] text-slate-500 italic">Nota: Las actualizaciones de productos existentes no consumen cupo del plan.</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                            <p class="font-black uppercase tracking-wider text-slate-800">Ejemplo de atributos</p>
                            <code class="mt-2 block rounded-lg bg-white p-2.5 text-[11px] leading-relaxed border border-slate-200">Marca=Lattafa; Concentración=EDP; Tamaño=100ml</code>
                        </div>
                    </aside>
                </div>

                @if ($rows !== [])
                    <!-- Warnings if any -->
                    @if (! empty($missingCategories))
                        <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900">
                            <p class="font-bold flex items-center gap-1.5 text-sm">
                                <span>⚠️</span> Categorías no encontradas en tu tienda
                            </p>
                            <p class="mt-1">Las siguientes categorías encontradas en el archivo no existen actualmente en tu tienda: <strong>{{ implode(', ', $missingCategories) }}</strong>.</p>
                            <label class="mt-3 flex items-center gap-2 cursor-pointer font-bold text-amber-950">
                                <input type="checkbox" name="create_missing_categories" value="1" form="import-confirm-form" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                                Crear estas categorías automáticamente en mi tienda durante la importación
                            </label>
                        </div>
                    @endif

                    @if ($mojibakeWarning)
                        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900">
                            <p class="font-bold flex items-center gap-1.5 text-sm">
                                <span>⚠️</span> Advertencia de codificación de caracteres (Mojibake)
                            </p>
                            <p class="mt-1">El archivo parece tener caracteres dañados por codificación. Por favor revisa que los nombres y descripciones en la vista previa sean legibles antes de confirmar.</p>
                        </div>
                    @endif

                    <!-- Preview Controls & Summary -->
                    <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-5 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
                            <div>
                                <h2 class="text-lg font-black text-slate-900">Vista previa del archivo</h2>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    {{ count($rows) }} filas en total · 
                                    <span class="font-bold text-emerald-700">{{ $validRows }} listas</span> · 
                                    <span class="font-bold text-rose-700">{{ $invalidRows }} necesitan revisión</span> · 
                                    <span class="font-bold text-indigo-700">{{ $newRowsCount ?? 0 }} nuevos</span> · 
                                    <span class="font-bold text-blue-700">{{ $existingRowsCount ?? 0 }} existentes</span>
                                </p>
                            </div>

                            @if ($validRows > 0)
                                <form id="import-confirm-form" method="POST" action="{{ route('seller.shops.products.import.store', $shop) }}">
                                    @csrf
                                    <input type="hidden" name="session_id" value="{{ $sessionId }}">
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-emerald-700 transition">
                                        Confirmar e importar productos válidos
                                    </button>
                                </form>
                            @else
                                <span class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-800">El archivo no contiene filas válidas para importar</span>
                            @endif
                        </div>

                        <!-- Duplicate Strategy Selector -->
                        <div class="grid gap-2 sm:grid-cols-3 text-xs">
                            <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-white p-3 cursor-pointer hover:border-indigo-300">
                                <input type="radio" name="duplicate_strategy" value="update" form="import-confirm-form" checked class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="font-bold text-slate-900 block">Actualizar existentes (Recomendado)</span>
                                    <span class="text-slate-500 text-[11px]">Actualiza precio, stock y datos si coincide por código o SKU.</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-white p-3 cursor-pointer hover:border-indigo-300">
                                <input type="radio" name="duplicate_strategy" value="skip" form="import-confirm-form" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="font-bold text-slate-900 block">Omitir existentes</span>
                                    <span class="text-slate-500 text-[11px]">Ignora los productos que ya existan sin modificarlos.</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-white p-3 cursor-pointer hover:border-indigo-300">
                                <input type="radio" name="duplicate_strategy" value="create" form="import-confirm-form" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="font-bold text-slate-900 block">Crear siempre nuevos</span>
                                    <span class="text-slate-500 text-[11px]">Crea productos adicionales si no colisiona el código de barras.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Table Preview with accessible states (Rule 8 compliant) -->
                    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">Fila</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Producto / Marca</th>
                                    <th class="px-4 py-3">SKU / Barcode</th>
                                    <th class="px-4 py-3">Categoría</th>
                                    <th class="px-4 py-3">Precio</th>
                                    <th class="px-4 py-3">Stock</th>
                                    <th class="px-4 py-3">Detalles</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($rows as $row)
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-4 py-3 font-mono text-slate-400 font-bold">{{ $row['line'] }}</td>
                                        
                                        <!-- Accessible Badges with Icon + Text -->
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if (! $row['valid'])
                                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-black text-rose-700 border border-rose-200">
                                                    <span>❌</span> Error
                                                </span>
                                            @elseif ($row['status'] === 'existing')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-black text-blue-700 border border-blue-200">
                                                    <span>🔵</span> Producto existente
                                                </span>
                                            @elseif ($row['status'] === 'possible_duplicate')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-black text-amber-800 border border-amber-200">
                                                    <span>⚠️</span> Revisión necesaria
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-black text-emerald-700 border border-emerald-200">
                                                    <span>✅</span> Listo para crear
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-4 py-3 max-w-xs">
                                            <p class="font-bold text-slate-900 leading-snug">{{ $row['name'] ?: 'Sin nombre' }}</p>
                                            @if ($row['brand'])<p class="text-[11px] text-slate-500">{{ $row['brand'] }}</p>@endif
                                        </td>

                                        <td class="px-4 py-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">
                                            @if ($row['product_code'])<p><span class="text-slate-400 text-[10px]">SKU:</span> {{ $row['product_code'] }}</p>@endif
                                            @if ($row['barcode'])<p><span class="text-slate-400 text-[10px]">EAN:</span> {{ $row['barcode'] }}</p>@endif
                                            @if (! $row['product_code'] && ! $row['barcode'])<span class="text-slate-400">—</span>@endif
                                        </td>

                                        <td class="px-4 py-3 text-slate-700">
                                            @if ($row['category'])
                                                <span>{{ $row['category'] }}</span>
                                                @if (($row['category_status'] ?? '') === 'missing')
                                                    <span class="block text-[10px] text-amber-700 font-bold">⚠️ No existe en tienda</span>
                                                @endif
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>

                                        <!-- Rule 8: Zero Distortion on Price & Numbers -->
                                        <td class="px-4 py-3 font-black text-slate-900 tabular-nums whitespace-nowrap">
                                            {{ $row['price'] !== null ? 'RD$ '.number_format((float) $row['price'], 2) : '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-slate-700 tabular-nums whitespace-nowrap">
                                            {{ $row['stock'] !== null ? $row['stock'] : 'No controlar' }}
                                        </td>

                                        <td class="px-4 py-3 text-[11px]">
                                            @if (! empty($row['errors']))
                                                <p class="text-rose-700 font-bold leading-tight">{{ implode(' ', $row['errors']) }}</p>
                                            @elseif (! empty($row['warnings']))
                                                <p class="text-amber-800 font-medium leading-tight">{{ implode(' ', $row['warnings']) }}</p>
                                            @elseif ($row['status'] === 'existing')
                                                <p class="text-blue-700 font-medium leading-tight">Coincide con producto en tienda ({{ $row['matched_product_name'] ?? '' }})</p>
                                            @else
                                                <span class="text-slate-400">Correcto</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <!-- Recent Import History Section (Requirement 27) -->
            @if ($recentSessions->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h2 class="text-base font-black text-slate-900">Historial reciente de importaciones</h2>
                    <p class="mt-1 text-xs text-slate-500">Últimas sesiones de importación registradas en esta tienda.</p>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="px-4 py-2.5">Fecha</th>
                                    <th class="px-4 py-2.5">Archivo</th>
                                    <th class="px-4 py-2.5">Usuario</th>
                                    <th class="px-4 py-2.5">Creados</th>
                                    <th class="px-4 py-2.5">Actualizados</th>
                                    <th class="px-4 py-2.5">Omitidos</th>
                                    <th class="px-4 py-2.5">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($recentSessions as $histSession)
                                    <tr>
                                        <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ $histSession->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-2.5 font-bold text-slate-900">{{ $histSession->original_filename }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ $histSession->user?->name ?? 'Sistema' }}</td>
                                        <td class="px-4 py-2.5 font-bold text-emerald-700 tabular-nums">{{ $histSession->summary['created'] ?? '—' }}</td>
                                        <td class="px-4 py-2.5 font-bold text-blue-700 tabular-nums">{{ $histSession->summary['updated'] ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-slate-500 tabular-nums">{{ $histSession->summary['skipped'] ?? '—' }}</td>
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            @if ($histSession->isConfirmed())
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Confirmado</span>
                                            @elseif ($histSession->isExpired())
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Expirado</span>
                                            @else
                                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-700">En vista previa</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </main>
</x-layouts.app>
