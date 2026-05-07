<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'store_id'    => Store::factory(),
            'category_id' => Category::factory(),
            'name'        => ucfirst($name),
            'slug'        => Str::slug($name) . '-' . $this->faker->unique()->numerify('####'),
            'description' => $this->faker->paragraph(),
            'status'      => ProductStatus::Active,
            'min_price'   => 0,
            'max_price'   => 0,
            'total_stock' => 0,
            'sold_count'  => 0,
            'rating_avg'  => 0,
            'weight_gram' => $this->faker->numberBetween(100, 5000),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Inactive]);
    }
}
