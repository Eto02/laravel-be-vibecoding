<?php

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'user_id'        => User::factory(),
            'name'           => $name,
            'slug'           => Str::slug($name) . '-' . Str::random(4),
            'description'    => $this->faker->paragraph(),
            'logo'           => null,
            'banner'         => null,
            'status'         => MerchantStatus::Active,
            'kyc_status'     => KycStatus::Pending,
            'city'           => $this->faker->city(),
            'province'       => 'Jawa Barat',
            'phone'          => null,
            'rating_avg'     => 0,
            'total_sales'    => 0,
            'follower_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => MerchantStatus::Pending]);
    }

    public function active(): static
    {
        return $this->state(['status' => MerchantStatus::Active]);
    }

    public function kycApproved(): static
    {
        return $this->state(['kyc_status' => KycStatus::Approved]);
    }

    public function kycSubmitted(): static
    {
        return $this->state(['kyc_status' => KycStatus::Submitted]);
    }
}
