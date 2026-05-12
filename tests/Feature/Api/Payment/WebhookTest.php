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
        $this->app->instance('payment.xendit', $mock);
        $this->app->instance('payment.midtrans', $mock);
    }

    private function mockInvalidGateway(): void
    {
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('verifyWebhook')->willReturn(false);

        $this->app->instance(PaymentGatewayInterface::class, $mock);
        $this->app->instance('payment.xendit', $mock);
        $this->app->instance('payment.midtrans', $mock);
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

    // ── Xendit QRIS webhook ───────────────────────────────────────────────────

    public function test_xendit_qris_webhook_marks_payment_paid(): void
    {
        Event::fake();

        // QRIS uses real parseWebhookPayload — no mock needed for parsing
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('verifyWebhook')->willReturn(true);

        // Use the real XenditPaymentService parseWebhookPayload
        $xendit = new \App\Services\Payment\XenditPaymentService();
        $mock->method('parseWebhookPayload')->willReturnCallback(
            fn ($req) => $xendit->parseWebhookPayload($req)
        );

        $this->app->instance(PaymentGatewayInterface::class, $mock);
        $this->app->instance('payment.xendit', $mock);

        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending]);
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'gateway_ref' => 'qr_9bd909fd-422a-4a43-bd49-958edf383f6d',
            'status'      => PaymentStatus::Pending,
            'method'      => 'qris',
        ]);
        Transaction::factory()->create([
            'external_id' => 'PAY-QRIS-TEST-1',
            'status'      => \App\Enums\TransactionStatus::Pending,
        ]);
        $payment->update(['transaction_id' => \App\Models\Transaction::where('external_id', 'PAY-QRIS-TEST-1')->first()->id]);

        $this->postJson('/api/webhooks/xendit', [
            'id'           => 'qrpy_f83566a0-81e6-4e4a-8677-44320f9fa7d3',
            'qr_id'        => 'qr_9bd909fd-422a-4a43-bd49-958edf383f6d',
            'reference_id' => 'PAY-QRIS-TEST-1',
            'status'       => 'SUCCEEDED',
            'amount'       => 1930,
            'currency'     => 'IDR',
            'type'         => 'DYNAMIC',
            'channel_code' => 'ID_LINKAJA',
        ])->assertStatus(200);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Paid->value]);
        Event::assertDispatched(PaymentCaptured::class);
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
