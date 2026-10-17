<?php

namespace App\Services;

/** Resolves legacy perfume bottle capacity from a product name. */
class LegacyBottleCapacityResolver
{
    /** @return array{volume_ml: int|null, source: string|null, error: string|null} */
    public function resolve(string $name): array
    {
        preg_match_all('/(?<!\d)(\d{1,4})\s*ml\b/iu', $name, $matches);
        $values = collect($matches[1] ?? [])->map(fn ($value) => (int) $value)->unique()->values();

        if ($values->count() > 1) {
            return ['volume_ml' => null, 'source' => null, 'error' => 'El nombre contiene más de una capacidad en ml.'];
        }
        if ($values->count() === 1) {
            $volume = (int) $values->first();
            if ($volume < 1 || $volume > 1000) {
                return ['volume_ml' => null, 'source' => null, 'error' => 'La capacidad indicada está fuera del rango admitido.'];
            }

            return ['volume_ml' => $volume, 'source' => 'name', 'error' => null];
        }

        if (preg_match('/\b\d+(?:[.,]\d+)?\s*(?:fl\.?\s*)?oz\b/iu', $name)
            || preg_match('/\b\d+(?:[.,]\d+)?\s*(?:l|liters?|litres?)\b/iu', $name)) {
            return ['volume_ml' => null, 'source' => null, 'error' => 'La capacidad está indicada en una unidad distinta de ml; requiere conversión revisada.'];
        }

        return ['volume_ml' => 100, 'source' => 'default', 'error' => null];
    }
}
