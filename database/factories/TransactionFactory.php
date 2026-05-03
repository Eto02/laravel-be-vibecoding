<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => 'INV-' . strtoupper($this->faker->lexify('????????????')) . '-' . time(),
            'amount'      => $this->faker->numberBetween(10000, 1000000),
            'status'      => TransactionStatus::Pending,
            'invoice_url' => 'https://checkout.xendit.co/web/' . $this->faker->uuid(),
            'paid_at'     => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'  => TransactionStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => TransactionStatus::Expired,
        ]);
    }
}
