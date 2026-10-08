<?php

namespace Database\Factories;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->sentence(3),
            // Products have a real unique(shop_id, slug) constraint. Keep
            // generated fixtures deterministic under MariaDB as well as
            // SQLite, where random slug collisions are not always exposed.
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'DOP',
            'availability_status' => ProductAvailabilityStatus::Available,
            'moderation_status' => ProductModerationStatus::Draft,
        ];
    }
}
