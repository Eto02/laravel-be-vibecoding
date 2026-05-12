<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => $this->faker->numberBetween(0, 100000000),
            'on_hold' => 0,
        ];
    }

    public function empty(): static
    {
        return $this->state(['balance' => 0, 'on_hold' => 0]);
    }
}
