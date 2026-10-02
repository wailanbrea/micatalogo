@props([
    'product' => null,
    'availableMl' => 0,
    'capacityMl' => 0,
    'label' => 'Nivel disponible',
    'storageKey' => 'liquid-level',
])

@php
    if ($product) {
        $liquidSource = $product->isDecant() ? $product->sourceProduct : $product;
        $liquidSourceInventory = $liquidSource?->inventory;
        $availableMl = $liquidSourceInventory?->available_ml
            ?? ($liquidSource?->sale_unit === 'ml' ? $liquidSourceInventory?->stock_quantity : null);
        $capacityMl = $liquidSource?->volume_ml;
        if ($liquidSource?->sale_unit === 'bottle' && $capacityMl && $availableMl > 0) {
            // Show the capacity of the bottles that still contain liquid, not one bottle size.
            $bottleCount = (int) ceil($availableMl / $capacityMl);
            $capacityMl *= max(1, $bottleCount);
        }
        $label = $product->isDecant() ? 'Nivel de la botella fuente' : 'Nivel de líquido disponible';
    }

    $available = max(0, (int) $availableMl);
    $capacity = max(0, (int) $capacityMl);
    $percentage = $capacity > 0 ? min(100, max(0, round(($available / $capacity) * 100, 1))) : 0;
    $showLiquidLevel = $product
        ? $product->isInventoryTracked()
            && in_array($product->sale_unit, ['ml', 'decant'], true)
            && $availableMl !== null
            && $capacity > 0
        : $capacity > 0;
@endphp

@if ($showLiquidLevel)
    <section
        class="liquid-level-card"
        data-liquid-level="{{ $percentage }}"
        data-liquid-key="{{ $storageKey }}"
        aria-label="{{ $label }}: {{ number_format($available) }} ml de {{ number_format($capacity) }} ml"
    >
        <div class="liquid-level__bottle" aria-hidden="true">
            <span class="liquid-level__neck"></span>
            <span class="liquid-level__body">
                <span class="liquid-level__fill" style="--liquid-level: {{ $percentage }}%"></span>
                <span class="liquid-level__shine"></span>
            </span>
        </div>

        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-sm font-extrabold text-slate-900">
                {{ number_format($available) }} ml
                <span class="font-medium text-slate-500">de {{ number_format($capacity) }} ml</span>
            </p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $capacity }}" aria-valuenow="{{ $available }}">
                <span class="block h-full rounded-full bg-amber-500 transition-[width] duration-700" style="width: {{ $percentage }}%"></span>
            </div>
        </div>
    </section>
@endif
