<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'  => Product::factory(),
            'sku'         => strtoupper($this->faker->unique()->bothify('SKU-##??##')),
            'price'       => $this->faker->numberBetween(1000000, 100000000),
            'stock'       => $this->faker->numberBetween(0, 500),
            'weight_gram' => $this->faker->numberBetween(100, 5000),
            'attributes'  => ['warna' => $this->faker->colorName(), 'ukuran' => $this->faker->randomElement(['S', 'M', 'L', 'XL'])],
        ];
    }
}
