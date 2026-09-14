<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->sentence(),
            'whatsapp_country_code' => '1',
            'whatsapp_number' => fake()->numerify('809#######'),
            'offers_shipping' => false,
            'status' => 'active',
        ];
    }
}
