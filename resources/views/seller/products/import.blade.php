<x-layouts.app :title="'Importar inventario | '.$shop->name">
    <x-admin.header :breadcrumbs="[
        ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
        ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
        ['label' => 'Importar inventario']
    ]" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <x-seller.shop-header :shop="$shop" activeTab="products" />

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">Importar inventario</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Carga un CSV o XLSX, revisa los datos y confirma solo las filas válidas. No se importa nada de forma silenciosa.</p>
                    </div>
                    <a href="{{ route('seller.shops.products.index', $shop) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Volver a productos</a>
                </div>

                @if ($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_280px]">
                    <form method="POST" action="{{ route('seller.shops.products.import.preview', $shop) }}" enctype="multipart/form-data" class="rounded-xl border border-dashed border-indigo-300 bg-indigo-50/40 p-6">
                        @csrf
                        <label for="inventory-file" class="block text-sm font-bold text-slate-900">Archivo de inventario</label>
                         <p class="mt-1 text-xs text-slate-500">Columnas reconocidas: nombre, código, barcode/EAN/GTIN, categoría, marca, precio, costo, stock, notas y atributos.</p>
                        <input id="inventory-file" name="file" type="file" accept=".csv,.txt,.xlsx" required class="mt-4 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                        <button type="submit" class="mt-4 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-indigo-700">Previsualizar archivo</button>
                    </form>

                    <aside class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-xs text-slate-600">
                        <p class="font-black uppercase tracking-wider text-slate-800">Ejemplo de atributos</p>
                        <code class="mt-3 block rounded-lg bg-white p-3 text-[11px] leading-relaxed">Marca=Rasasi; Concentración=EDP; Presentación=100 ml</code>
                        <p class="mt-3">Usa la primera fila como encabezado. Las filas sin nombre o precio quedan marcadas para revisión.</p>
                    </aside>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-black text-slate-900">Cupo de productos</p>
                    <p class="mt-1">Esta tienda tiene <strong>{{ $quota['product_count'] }}</strong> de <strong>{{ $quota['product_limit'] }}</strong> productos permitidos por su plan.</p>
                    <p class="mt-1">Puedes importar hasta <strong>{{ $quota['products_remaining'] }}</strong> productos más.</p>
                </div>

                <section class="mt-6 rounded-xl border border-blue-200 bg-blue-50/60 p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-black text-white">i</span>
                        <div class="min-w-0">
                            <h2 class="text-base font-black text-slate-900">¿Tu archivo tiene otro formato?</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Puedes pedirle a ChatGPT que lo adapte antes de subirlo.</p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-slate-800"><strong>Recomendado: conviértelo a Excel <code>.xlsx</code> para evitar problemas de codificación.</strong></p>
                    <p class="mt-4 text-sm font-bold text-slate-800">Copia este mensaje:</p>
                    <blockquote class="mt-3 space-y-4 rounded-lg border-l-4 border-blue-400 bg-white p-4 text-sm leading-6 text-slate-700 sm:p-5">
                        <p>Convierte este archivo al formato compatible con MiCatalogo.</p>
                        <p>Crea preferiblemente un archivo Excel <code>.xlsx</code> listo para descargar.</p>
                        <p>Usa exactamente estas columnas y en este orden:</p>
                        <code class="block break-words rounded-md bg-slate-50 p-3 text-xs">nombre, sku, marca, categoria, descripcion, precio, costo, stock, notas, atributos</code>
                        <p>Reglas obligatorias:</p>
                        <ul class="list-disc space-y-2 pl-5">
                            <li>La primera fila debe contener exactamente esos encabezados, sin espacios adicionales ni caracteres ocultos.</li>
                            <li>No agregues BOM ni caracteres invisibles al encabezado <code>nombre</code>.</li>
                            <li>Conserva correctamente tildes, ñ y demás caracteres Unicode. No deben aparecer textos dañados como <code>Ã±</code>, <code>Ã¡</code>, <code>ï»¿</code> u otros similares.</li>
                            <li><code>nombre</code> y <code>precio</code> son obligatorios.</li>
                            <li>Si un dato no existe, deja la celda vacía. No inventes información.</li>
                            <li><code>sku</code> debe guardarse como texto, aunque contenga solamente números, para no perder ceros iniciales ni precisión.</li>
                            <li><code>precio</code>, <code>costo</code> y <code>stock</code> deben guardarse como valores numéricos cuando existan.</li>
                            <li>No cambies nombres de productos, marcas o categorías salvo para corregir errores claros de codificación.</li>
                            <li>No agregues filas explicativas, títulos, comentarios ni texto fuera de la tabla.</li>
                            <li>Antes de entregar el archivo, valida que el encabezado sea exactamente <code>nombre</code> y no <code>ï»¿nombre</code>.</li>
                        </ul>
                        <p>Si no puedes generar <code>.xlsx</code>, genera un archivo <code>.csv</code> codificado en <strong>UTF-8 sin BOM</strong>, usando exactamente las mismas columnas y reglas.</p>
                        <p>Devuelve únicamente el archivo final listo para importar en MiCatalogo.</p>
                    </blockquote>
                </section>

                @if ($rows !== [])
                    <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Vista previa</h2>
                            <p class="text-xs text-slate-500">{{ count($rows) }} filas encontradas · <span class="font-bold text-emerald-700">{{ $validRows }} listas</span> · <span class="font-bold text-rose-700">{{ $invalidRows }} necesitan revisión</span> · {{ $quota['products_remaining'] }} disponibles en el cupo</p>
                        </div>
                        @if ($validRows > 0 && $validRows <= $quota['products_remaining'])
                            <form method="POST" action="{{ route('seller.shops.products.import.store', $shop) }}">
                                @csrf
                                <input type="hidden" name="rows" value="{{ json_encode(collect($rows)->where('valid', true)->values()->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-emerald-700">Importar {{ $validRows }} productos válidos</button>
                            </form>
                        @elseif ($validRows > $quota['products_remaining'])
                            <span class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">El archivo supera el cupo disponible</span>
                        @endif
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr><th class="px-4 py-3">Fila</th><th class="px-4 py-3">Producto</th><th class="px-4 py-3">Precio</th><th class="px-4 py-3">Stock</th><th class="px-4 py-3">Estado</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-slate-400">{{ $row['line'] }}</td>
                                        <td class="px-4 py-3"><p class="font-bold text-slate-900">{{ $row['name'] ?: 'Sin nombre' }}</p><p class="text-slate-500">{{ $row['brand'] ?: 'Sin marca' }}</p></td>
                                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $row['price'] !== null ? 'RD$ '.number_format($row['price'], 2) : '—' }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $row['stock'] ?? 'No controlar' }}</td>
                                        <td class="px-4 py-3">@if ($row['valid'])<span class="rounded-full bg-emerald-50 px-2 py-1 font-bold text-emerald-700">Lista</span>@else<span class="rounded-full bg-rose-50 px-2 py-1 font-bold text-rose-700">{{ implode(' ', $row['errors']) }}</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
