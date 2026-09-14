<?php

namespace Database\Factories;

use App\Models\GlobalCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalCategory>
 */
class GlobalCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'status' => 'active',
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
