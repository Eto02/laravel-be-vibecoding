<?php

namespace Tests\Feature\Api\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentCaptured;
use App\Events\Payment\PaymentFailed;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function mockGateway(string $status = 'paid', string $externalId = 'PAY-TEST'): void
    {
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('verifyWebhook')->willReturn(true);
        $mock->method('parseWebhookPayload')->willReturn([
            'event'       => $status === 'paid' ? 'payment.succeeded' : 'payment.failed',
            'external_id' => $externalId,
            'status'      => $status,
            'amount'      => 10000000,
        ]);

        $this->app->instance(PaymentGatewayInterface::class, $mock);
    }

    private function mockInvalidGateway(): void
    {
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('verifyWebhook')->willReturn(false);

        $this->app->instance(PaymentGatewayInterface::class, $mock);
    }

    private function paymentWithOrder(string $gatewayRef = 'PAY-TEST'): array
    {
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'total' => 10000000]);
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'gateway_ref' => $gatewayRef,
            'status'      => PaymentStatus::Pending,
            'amount'      => 10000000,
        ]);

        return compact('order', 'payment');
    }

    // ── Xendit webhook ────────────────────────────────────────────────────────

    public function test_xendit_paid_webhook_marks_payment_paid(): void
    {
        Event::fake();
        $this->mockGateway('paid', 'PAY-TEST');
        ['order' => $order, 'payment' => $payment] = $this->paymentWithOrder('PAY-TEST');

        $response = $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-TEST', 'status' => 'PAID']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Paid->value]);
        Event::assertDispatched(PaymentCaptured::class);
    }

    public function test_xendit_expired_webhook_marks_payment_expired(): void
    {
        Event::fake();
        $this->mockGateway('expired', 'PAY-TEST');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-TEST');

        $response = $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-TEST', 'status' => 'EXPIRED']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Expired->value]);
        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_invalid_webhook_signature_returns_403(): void
    {
        $this->mockInvalidGateway();

        $response = $this->postJson('/api/webhooks/xendit', ['external_id' => 'X', 'status' => 'PAID']);

        $response->assertStatus(403);
    }

    public function test_webhook_is_idempotent_for_already_paid_payment(): void
    {
        Event::fake();
        $this->mockGateway('paid', 'PAY-PAID');
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid, 'total' => 10000000]);
        $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'gateway_ref' => 'PAY-PAID']);

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-PAID', 'status' => 'PAID'])
            ->assertStatus(200);

        // Event should NOT be dispatched again
        Event::assertNotDispatched(PaymentCaptured::class);
    }

    // ── Midtrans webhook ──────────────────────────────────────────────────────

    public function test_midtrans_webhook_marks_payment_paid(): void
    {
        Event::fake();
        $this->mockGateway('paid', 'PAY-TEST');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-TEST');

        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id'            => 'PAY-TEST',
            'transaction_status'  => 'settlement',
            'status_code'         => '200',
            'gross_amount'        => '100000.00',
            'signature_key'       => 'mock_verified',
        ]);

        $response->assertStatus(200);
        Event::assertDispatched(PaymentCaptured::class);
    }
}
