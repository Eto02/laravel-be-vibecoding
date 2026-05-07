<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductMedia> */
class ProductMediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'file'       => 'products/' . $this->faker->uuid() . '.jpg',
            'type'       => 'image',
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true, 'sort_order' => 0]);
    }
}
