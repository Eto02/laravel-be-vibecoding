<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreDocument>
 */
class StoreDocumentFactory extends Factory
{
    protected $model = StoreDocument::class;

    public function definition(): array
    {
        return [
            'store_id'    => Store::factory(),
            'type'        => $this->faker->randomElement(['ktp', 'npwp', 'siup']),
            'file'        => 'kyc/' . $this->faker->uuid() . '.jpg',
            'status'      => 'pending',
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status'      => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status'      => 'rejected',
            'reviewed_at' => now(),
        ]);
    }
}
