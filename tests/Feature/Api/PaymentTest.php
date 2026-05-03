<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function fakeXenditInvoice(int $amount = 50000): void
    {
        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response([
                'id'          => 'inv_test_' . uniqid(),
                'external_id' => 'INV-TESTXXXXXXXX-' . time(),
                'invoice_url' => 'https://checkout.xendit.co/web/test-' . uniqid(),
                'status'      => 'PENDING',
                'amount'      => $amount,
            ], 200),
        ]);
    }

    // =========================================================================
    // CREATE PAYMENT
    // =========================================================================

    public function test_authenticated_user_can_create_payment(): void
    {
        $this->fakeXenditInvoice(50000);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'amount'      => 50000,
            'description' => 'Test payment',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['id', 'external_id', 'amount', 'status', 'invoice_url', 'created_at'],
                     'meta' => ['timestamp'],
                 ])
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'amount' => 50000,
                         'status' => 'pending',
                     ],
                 ]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 50000,
            'status' => 'pending',
        ]);
    }

    public function test_payment_invoice_url_is_stored(): void
    {
        $this->fakeXenditInvoice(100000);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/payments', ['amount' => 100000]);

        $transaction = \App\Models\Transaction::where('amount', 100000)->first();
        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->invoice_url);
        $this->assertStringStartsWith('https://checkout.xendit.co/web/', $transaction->invoice_url);
    }

    public function test_unauthenticated_user_cannot_create_payment(): void
    {
        $response = $this->postJson('/api/payments', [
            'amount' => 50000,
        ]);

        $response->assertStatus(401);
    }

    public function test_payment_fails_with_amount_below_minimum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'amount' => 5000,
        ]);

        $response->assertStatus(422);
    }

    public function test_payment_fails_with_missing_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', []);

        $response->assertStatus(422);
    }

    public function test_payment_fails_with_non_integer_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'amount' => 'not-a-number',
        ]);

        $response->assertStatus(422);
    }

    public function test_payment_fails_with_invalid_currency(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/payments', [
            'amount'   => 50000,
            'currency' => 'USD',
        ]);

        $response->assertStatus(422);
    }

}
