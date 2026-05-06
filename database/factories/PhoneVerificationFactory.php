<?php

namespace Database\Factories;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhoneVerification>
 */
class PhoneVerificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'phone'      => '+628' . fake()->numerify('#########'),
            'otp_hash'   => hash('sha256', fake()->numerify('######')),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }

    public function verified(): static
    {
        return $this->state(['verified_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinutes(10)]);
    }
}
