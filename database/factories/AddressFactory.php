<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'label'          => fake()->randomElement(['Rumah', 'Kantor', 'Kost']),
            'recipient_name' => fake()->name(),
            'phone'          => '+628' . fake()->numerify('#########'),
            'province'       => fake()->randomElement(['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'DKI Jakarta', 'Banten']),
            'city'           => fake()->city(),
            'district'       => fake()->word() . ' Selatan',
            'postal_code'    => fake()->numerify('#####'),
            'street'         => fake()->streetAddress(),
            'lat'            => fake()->latitude(-10, 6),
            'lng'            => fake()->longitude(95, 141),
            'is_default'     => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
