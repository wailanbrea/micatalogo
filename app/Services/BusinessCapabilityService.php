<?php

namespace App\Services;

use App\Models\Shop;

class BusinessCapabilityService
{
    public function __construct(private readonly BusinessProfileService $profiles) {}

    public function assert(Shop $shop, string $capability): void
    {
        abort_if(! $this->profiles->allows($shop, $capability), 422, 'Esta función no está disponible para el tipo de negocio y plan de la tienda.');
    }

    public function payload(Shop $shop): array
    {
        return $this->profiles->profile($shop);
    }
}
