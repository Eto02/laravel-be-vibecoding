<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id'        => Order::factory(),
            'transaction_id'  => null,
            'gateway'         => 'xendit',
            'method'          => 'virtual_account',
            'gateway_ref'     => 'va-' . $this->faker->uuid(),
            'amount'          => $this->faker->numberBetween(10000, 50000000),
            'status'          => PaymentStatus::Pending,
            'payment_details' => ['bank_code' => 'BCA', 'account_number' => '8808' . $this->faker->numerify('########')],
            'expires_at'      => now()->addHours(24),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentStatus::Paid, 'expires_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(['status' => PaymentStatus::Expired, 'expires_at' => now()->subHour()]);
    }

    public function xenditQris(): static
    {
        return $this->state([
            'method'          => 'qris',
            'payment_details' => ['qr_id' => 'qr-' . $this->faker->uuid(), 'qr_string' => '00020101...'],
            'expires_at'      => now()->addMinutes(5),
        ]);
    }

    public function midtransSnap(): static
    {
        return $this->state([
            'gateway' => 'midtrans',
            'method'  => 'snap',
            'payment_details' => ['snap_token' => $this->faker->uuid(), 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/' . $this->faker->uuid()],
        ]);
    }
}
