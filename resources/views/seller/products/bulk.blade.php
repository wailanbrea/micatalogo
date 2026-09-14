<x-layouts.app :title="'Subida masiva | '.$shop->name">
    <!-- Persistent Unified Navigation -->
    <x-admin.header 
        :breadcrumbs="[
            ['label' => 'Mis tiendas', 'url' => route('seller.dashboard')],
            ['label' => $shop->name, 'url' => route('seller.shops.products.index', $shop)],
            ['label' => 'Subida masiva']
        ]" 
    />

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8 text-slate-800">
        <div class="mx-auto max-w-7xl">
            <!-- Shop Context & Local Navigation Tabs -->
            <x-seller.shop-header :shop="$shop" activeTab="bulk" />
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">Subida Masiva de Productos</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Carga múltiples productos al mismo tiempo. Selecciona tus fotos, ajusta nombres y precios, y publica tu lote de una sola vez.
                    </p>
                </div>
                <div>
                    <span class="rounded-full bg-blue-50 px-3.5 py-1 text-xs font-semibold text-blue-700 border border-blue-200">
                        {{ $remainingQuota }} cupos libres de {{ $maxQuota }}
                    </span>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <p class="font-bold">Por favor corrige los siguientes errores:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($remainingQuota <= 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-800">
                    <h3 class="font-bold">Has alcanzado el límite de productos de tu plan</h3>
                    <p class="mt-1 text-sm">Tu tienda ya cuenta con {{ $usedQuota }} productos. Elimina algunos para liberar espacio antes de cargar nuevos.</p>
                    <a class="mt-4 inline-block rounded-md bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700" href="{{ route('seller.shops.products.index', $shop) }}">
                        Administrar mis productos
                    </a>
                </div>
            @else
                <!-- Dropzone / Picker Area -->
                <div class="mb-8 rounded-xl border-2 border-dashed border-slate-300 bg-white p-8 text-center transition hover:border-blue-400" id="dropzone">
                    <input type="file" id="batch-file-input" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h2 class="mt-3 text-base font-bold text-slate-800">Selecciona o arrastra las fotos de tus productos</h2>
                    <p class="mt-1 text-xs text-slate-500">Puedes cargar hasta 30 imágenes a la vez (máximo {{ min(30, $remainingQuota) }} productos permitidos en este lote).</p>
                    <div class="mt-4 flex justify-center gap-3">
                        <button type="button" onclick="document.getElementById('batch-file-input').click()" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-xs hover:bg-blue-700">
                            Elegir archivos
                        </button>
                        <button type="button" onclick="addBlankRow()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            + Agregar fila sin foto
                        </button>
                    </div>
                </div>

                <!-- Bulk Form -->
                <form action="{{ route('seller.shops.products.bulk.store', $shop) }}" method="POST" enctype="multipart/form-data" id="bulk-form">
                    @csrf

                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">
                            Productos en cola (<span id="items-count">0</span>)
                        </h2>
                        <div id="form-actions" class="hidden">
                            <button type="button" onclick="clearAllRows()" class="text-xs font-semibold text-rose-600 hover:underline">
                                Vaciar lista
                            </button>
                        </div>
                    </div>

                    <div id="items-container" class="space-y-4">
                        <!-- Dynamic items will be injected here -->
                    </div>

                    <div id="empty-state" class="rounded-lg border border-dashed border-slate-200 bg-white p-12 text-center text-slate-400">
                        <p class="text-sm font-medium">Aún no has agregado productos a la cola.</p>
                        <p class="text-xs mt-1">Haz clic en "Elegir archivos" para comenzar.</p>
                    </div>

                    <!-- Submit Bar -->
                    <div id="submit-bar" class="mt-8 hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-bold text-slate-800">¿Todo listo?</p>
                            <p class="text-xs text-slate-500">Los productos se publicarán de inmediato con estado "Disponible".</p>
                        </div>
                        <div class="mt-3 flex items-center gap-3 sm:mt-0">
                            <a href="{{ route('seller.shops.products.index', $shop) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Cancelar
                            </a>
                            <button type="submit" id="submit-btn" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-emerald-700">
                                Publicar todos los productos
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </main>
    </div>

    <!-- Script for Dynamic Rows and File Previews -->
    <script>
        const categories = @json($categories);
        const globalCategories = @json($globalCategories);
        const maxQuota = {{ min(30, $remainingQuota) }};
        let rowCounter = 0;

        const fileInput = document.getElementById('batch-file-input');
        const itemsContainer = document.getElementById('items-container');
        const emptyState = document.getElementById('empty-state');
        const submitBar = document.getElementById('submit-bar');
        const formActions = document.getElementById('form-actions');
        const itemsCountSpan = document.getElementById('items-count');
        const dropzone = document.getElementById('dropzone');

        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
                fileInput.value = '';
            });
        }

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('border-blue-500', 'bg-blue-50/30');
                });
            });

            ['dragleave', 'drop'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('border-blue-500', 'bg-blue-50/30');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    handleFiles(e.dataTransfer.files);
                }
            });
        }

        function handleFiles(files) {
            const currentRows = itemsContainer.querySelectorAll('.product-row').length;
            const availableSlots = maxQuota - currentRows;

            if (availableSlots <= 0) {
                alert('Ya has alcanzado el límite máximo de ' + maxQuota + ' productos para este lote.');
                return;
            }

            const filesToProcess = Array.from(files).slice(0, availableSlots);
            if (files.length > availableSlots) {
                alert('Solo se agregaron ' + availableSlots + ' archivos para no exceder el límite permitido.');
            }

            filesToProcess.forEach(file => {
                const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                const cleanName = nameWithoutExt.replace(/[_\-+]/g, ' ').trim();
                const formattedName = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);

                createProductRow({
                    file: file,
                    name: formattedName,
                    price: '',
                    previewUrl: URL.createObjectURL(file)
                });
            });

            updateUIState();
        }

        function addBlankRow() {
            const currentRows = itemsContainer.querySelectorAll('.product-row').length;
            if (currentRows >= maxQuota) {
                alert('Has alcanzado el límite máximo de ' + maxQuota + ' productos para este lote.');
                return;
            }

            createProductRow({
                file: null,
                name: '',
                price: '',
                previewUrl: null
            });

            updateUIState();
        }

        function createProductRow(data) {
            const index = rowCounter++;
            const row = document.createElement('div');
            row.className = 'product-row rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-slate-300';
            row.id = `row-${index}`;

            let categoryOptions = '<option value="">Sin categoría interna</option>';
            categories.forEach(cat => {
                categoryOptions += `<option value="${cat.id}">${cat.name}</option>`;
            });

            let globalCategoryOptions = '<option value="">Categoría global (opcional)</option>';
            globalCategories.forEach(cat => {
                globalCategoryOptions += `<option value="${cat.id}">${cat.name}</option>`;
            });

            row.innerHTML = `
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <!-- Image Preview -->
                    <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-slate-100 flex items-center justify-center border border-slate-200">
                        ${data.previewUrl 
                            ? `<img src="${data.previewUrl}" class="h-full w-full object-cover object-center" alt="Preview">` 
                            : `<svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>`
                        }
                    </div>

                    <!-- Fields -->
                    <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-bold text-slate-700">Nombre del producto *</label>
                            <input type="text" name="products[${index}][name]" value="${escapeHtml(data.name)}" required maxlength="120" placeholder="Ej: Camisa Lino Blanca" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold text-slate-700">Precio (RD$) *</label>
                            <input type="number" step="0.01" min="0" max="9999999.99" name="products[${index}][price]" value="${escapeHtml(data.price)}" required placeholder="0.00" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div class="sm:col-span-4">
                            <label class="block text-xs font-bold text-slate-700">Categoría interna</label>
                            <select name="products[${index}][shop_category_id]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-900 focus:border-blue-600 focus:ring-blue-600">
                                ${categoryOptions}
                            </select>
                        </div>

                        <div class="sm:col-span-12">
                            <input type="text" name="products[${index}][description]" placeholder="Descripción corta (opcional)" maxlength="2000" class="w-full rounded-md border border-slate-200 px-3 py-1 text-xs text-slate-600 placeholder-slate-400 focus:border-blue-600">
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="shrink-0 flex items-center self-center sm:self-start">
                        <button type="button" onclick="removeRow(${index})" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-md hover:bg-slate-100" title="Eliminar fila">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            `;

            // Append dynamic file input using DataTransfer
            if (data.file) {
                const hiddenFileInput = document.createElement('input');
                hiddenFileInput.type = 'file';
                hiddenFileInput.name = `products[${index}][image]`;
                hiddenFileInput.className = 'hidden';

                const dt = new DataTransfer();
                dt.items.add(data.file);
                hiddenFileInput.files = dt.files;
                row.appendChild(hiddenFileInput);
            }

            itemsContainer.appendChild(row);
        }

        function removeRow(index) {
            const row = document.getElementById(`row-${index}`);
            if (row) {
                row.remove();
                updateUIState();
            }
        }

        function clearAllRows() {
            if (confirm('¿Deseas vaciar todos los productos cargados?')) {
                itemsContainer.innerHTML = '';
                updateUIState();
            }
        }

        function updateUIState() {
            const rowsCount = itemsContainer.querySelectorAll('.product-row').length;
            itemsCountSpan.textContent = rowsCount;

            if (rowsCount > 0) {
                emptyState.classList.add('hidden');
                submitBar.classList.remove('hidden');
                formActions.classList.remove('hidden');
            } else {
                emptyState.classList.remove('hidden');
                submitBar.classList.add('hidden');
                formActions.classList.add('hidden');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</x-layouts.app>
