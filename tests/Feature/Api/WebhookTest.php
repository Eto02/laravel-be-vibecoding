<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $validToken = 'test_webhook_token_for_phpunit';

    protected function setUp(): void
    {
        parent::setUp();
        config(['xendit.webhook_token' => $this->validToken]);
    }

    private function makeTransaction(TransactionStatus $status = TransactionStatus::Pending): Transaction
    {
        return Transaction::factory()->create(['status' => $status]);
    }

    private function postWebhook(array $payload, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-CALLBACK-TOKEN', $token ?? $this->validToken)
                    ->postJson('/api/webhooks/xendit', $payload);
    }

    // =========================================================================
    // PAID
    // =========================================================================

    public function test_xendit_webhook_updates_transaction_to_paid(): void
    {
        $transaction = $this->makeTransaction();

        $response = $this->postWebhook([
            'external_id' => $transaction->external_id,
            'status'      => 'PAID',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'message', 'meta' => ['timestamp']])
                 ->assertJson(['success' => true]);

        $updated = $transaction->fresh();
        $this->assertEquals(TransactionStatus::Paid, $updated->status);
        $this->assertNotNull($updated->paid_at);
    }

    // =========================================================================
    // EXPIRED
    // =========================================================================

    public function test_xendit_webhook_updates_transaction_to_expired(): void
    {
        $transaction = $this->makeTransaction();

        $response = $this->postWebhook([
            'external_id' => $transaction->external_id,
            'status'      => 'EXPIRED',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $updated = $transaction->fresh();
        $this->assertEquals(TransactionStatus::Expired, $updated->status);
        $this->assertNull($updated->paid_at);
    }

    // =========================================================================
    // SECURITY — TOKEN VALIDATION
    // =========================================================================

    public function test_xendit_webhook_rejects_invalid_token(): void
    {
        $transaction = $this->makeTransaction();

        $response = $this->postWebhook([
            'external_id' => $transaction->external_id,
            'status'      => 'PAID',
        ], 'wrong_token');

        $response->assertStatus(403)->assertJson(['success' => false]);

        $this->assertEquals(TransactionStatus::Pending, $transaction->fresh()->status);
    }

    public function test_xendit_webhook_rejects_missing_token(): void
    {
        $transaction = $this->makeTransaction();

        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => $transaction->external_id,
            'status'      => 'PAID',
        ]);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_xendit_webhook_ignores_unknown_status(): void
    {
        $transaction = $this->makeTransaction();

        $response = $this->postWebhook([
            'external_id' => $transaction->external_id,
            'status'      => 'SETTLEMENT',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertEquals(TransactionStatus::Pending, $transaction->fresh()->status);
    }

    public function test_xendit_webhook_paid_transaction_has_paid_at_timestamp(): void
    {
        $transaction = $this->makeTransaction();

        $this->postWebhook([
            'external_id' => $transaction->external_id,
            'status'      => 'PAID',
        ]);

        $this->assertDatabaseHas('transactions', [
            'external_id' => $transaction->external_id,
            'status'      => 'paid',
        ]);

        $this->assertNotNull($transaction->fresh()->paid_at);
    }
}
