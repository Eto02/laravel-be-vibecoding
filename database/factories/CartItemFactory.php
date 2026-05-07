<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id'            => Cart::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_id'         => Product::factory(),
            'store_id'           => Store::factory(),
            'quantity'           => $this->faker->numberBetween(1, 5),
            'price_snapshot'     => $this->faker->numberBetween(10000, 1000000),
        ];
    }
}
