<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreFollower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreFollower>
 */
class StoreFollowerFactory extends Factory
{
    protected $model = StoreFollower::class;

    public function definition(): array
    {
        return [
            'store_id'   => Store::factory(),
            'user_id'    => User::factory(),
            'created_at' => now(),
        ];
    }
}
