<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopCategory>
 */
class ShopCategoryFactory extends Factory
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
            'name' => fake()->words(2, true),
            'slug' => fake()->slug(2),
            'status' => 'active',
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
