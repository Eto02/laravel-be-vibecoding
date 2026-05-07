<?php

namespace Database\Factories;

use App\Enums\DisputeStatus;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDispute>
 */
class OrderDisputeFactory extends Factory
{
    protected $model = OrderDispute::class;

    public function definition(): array
    {
        return [
            'order_id'    => Order::factory(),
            'user_id'     => User::factory(),
            'reason'      => fake()->randomElement(['item_not_received', 'item_damaged', 'wrong_item', 'not_as_described']),
            'description' => fake()->paragraph(),
            'status'      => DisputeStatus::Open,
            'resolution'  => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status'     => DisputeStatus::Resolved,
            'resolution' => fake()->paragraph(),
        ]);
    }
}
