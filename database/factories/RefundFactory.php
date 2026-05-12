<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id'  => Payment::factory()->paid(),
            'amount'      => $this->faker->numberBetween(10000, 5000000),
            'reason'      => $this->faker->randomElement(['Item not received', 'Wrong item', 'Item damaged']),
            'status'      => RefundStatus::Pending,
            'gateway_ref' => null,
            'refunded_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state([
            'status'      => RefundStatus::Processed,
            'gateway_ref' => 'ref-' . $this->faker->uuid(),
            'refunded_at' => now(),
        ]);
    }
}
