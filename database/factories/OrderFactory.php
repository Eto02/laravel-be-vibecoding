<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number'     => null,
            'user_id'          => User::factory(),
            'store_id'         => Store::factory(),
            'address_snapshot' => [
                'label'      => 'Rumah',
                'recipient'  => fake()->name(),
                'phone'      => '08' . fake()->numerify('#########'),
                'address'    => fake()->streetAddress(),
                'city'       => fake()->city(),
                'province'   => fake()->state(),
                'postal_code' => fake()->postcode(),
            ],
            'subtotal'         => fake()->numberBetween(10000, 500000) * 100,
            'shipping_fee'     => fake()->numberBetween(1000, 50000) * 100,
            'discount'         => 0,
            'total'            => fn (array $attrs) => $attrs['subtotal'] + $attrs['shipping_fee'] - $attrs['discount'],
            'shipping_courier' => fake()->randomElement(['jne', 'jnt', 'sicepat', 'anteraja']),
            'shipping_service' => fake()->randomElement(['REG', 'YES', 'OKE']),
            'status'           => OrderStatus::Pending,
            'payment_due_at'   => now()->addHours(24),
            'notes'            => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid]);
    }

    public function processing(): static
    {
        return $this->state(['status' => OrderStatus::Processing]);
    }

    public function shipped(): static
    {
        return $this->state(['status' => OrderStatus::Shipped]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }
}
