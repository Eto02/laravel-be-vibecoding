<?php

namespace Tests\Feature\Api\Payment;

use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create([
            'role'              => UserRole::Buyer,
            'email_verified_at' => now(),
        ]);
    }

    private function merchant(): User
    {
        $user  = User::factory()->create(['role' => UserRole::Merchant, 'email_verified_at' => now()]);
        Store::factory()->create([
            'user_id'    => $user->id,
            'status'     => MerchantStatus::Active,
            'kyc_status' => KycStatus::Approved,
        ]);
        return $user;
    }

    // ── balance ───────────────────────────────────────────────────────────────

    public function test_user_can_get_wallet_balance(): void
    {
        $buyer = $this->buyer();

        $response = $this->actingAs($buyer)->getJson('/api/wallet/balance');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => ['balance_cents', 'balance', 'on_hold_cents', 'available_cents'],
        ]);
    }

    public function test_balance_creates_wallet_on_demand(): void
    {
        $buyer = $this->buyer();
        $this->assertDatabaseMissing('wallet_balances', ['user_id' => $buyer->id]);

        $this->actingAs($buyer)->getJson('/api/wallet/balance')->assertStatus(200);

        $this->assertDatabaseHas('wallet_balances', ['user_id' => $buyer->id, 'balance' => 0]);
    }

    public function test_balance_requires_auth(): void
    {
        $this->getJson('/api/wallet/balance')->assertStatus(401);
    }

    // ── transactions ──────────────────────────────────────────────────────────

    public function test_user_can_list_wallet_transactions(): void
    {
        $buyer  = $this->buyer();
        $wallet = WalletBalance::factory()->create(['user_id' => $buyer->id]);
        $wallet->transactions()->createMany([
            ['type' => 'credit', 'amount' => 50000000, 'description' => 'Top-up'],
            ['type' => 'debit',  'amount' => 10000000, 'description' => 'Purchase'],
        ]);

        $response = $this->actingAs($buyer)->getJson('/api/wallet/transactions');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => [['id', 'type', 'amount_cents', 'amount', 'description']],
        ]);
    }

    // ── withdraw ──────────────────────────────────────────────────────────────

    public function test_merchant_can_initiate_withdrawal(): void
    {
        $merchant = $this->merchant();
        WalletBalance::factory()->create(['user_id' => $merchant->id, 'balance' => 50000000]);

        $response = $this->actingAs($merchant)->postJson('/api/wallet/withdraw', [
            'amount'         => 10000000,
            'bank_code'      => 'BCA',
            'account_number' => '1234567890',
            'account_name'   => 'Test Merchant',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['data' => ['balance_cents', 'on_hold_cents']]);
        $this->assertDatabaseHas('wallet_balances', ['user_id' => $merchant->id, 'on_hold' => 10000000]);
    }

    public function test_withdraw_fails_with_insufficient_balance(): void
    {
        $merchant = $this->merchant();
        WalletBalance::factory()->create(['user_id' => $merchant->id, 'balance' => 5000000]);

        $response = $this->actingAs($merchant)->postJson('/api/wallet/withdraw', [
            'amount'         => 10000000,
            'bank_code'      => 'BCA',
            'account_number' => '1234567890',
            'account_name'   => 'Test Merchant',
        ]);

        $response->assertStatus(422);
    }

    public function test_buyer_cannot_withdraw(): void
    {
        $buyer = $this->buyer();

        $response = $this->actingAs($buyer)->postJson('/api/wallet/withdraw', [
            'amount'         => 10000000,
            'bank_code'      => 'BCA',
            'account_number' => '1234567890',
            'account_name'   => 'Test',
        ]);

        $response->assertStatus(403);
    }
}
