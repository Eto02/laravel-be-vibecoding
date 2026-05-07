<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(5000, 200000) * 100;
        $quantity  = fake()->numberBetween(1, 5);

        return [
            'order_id'           => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_snapshot'   => [
                'product_name'  => fake()->words(3, true),
                'variant_sku'   => strtoupper(fake()->bothify('SKU-##??##')),
                'attributes'    => ['warna' => fake()->colorName()],
                'weight_gram'   => fake()->numberBetween(100, 5000),
            ],
            'quantity'           => $quantity,
            'unit_price'         => $unitPrice,
            'subtotal'           => $unitPrice * $quantity,
        ];
    }
}
