<?php

namespace Database\Factories;

use App\Enums\ProductImageProcessingStatus;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'object_key' => 'products/'.fake()->uuid().'/main.webp',
            'thumbnail_object_key' => 'products/'.fake()->uuid().'/thumb.webp',
            'mime_type' => 'image/webp',
            'width' => 1600,
            'height' => 1200,
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'checksum_sha256' => hash('sha256', fake()->uuid()),
            'sort_order' => 0,
            'processing_status' => ProductImageProcessingStatus::Ready,
        ];
    }
}
