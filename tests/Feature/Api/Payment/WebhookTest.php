<?php

namespace Tests\Feature\Api\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentCaptured;
use App\Events\Payment\PaymentFailed;
use App\Mail\Payment\PaymentExpiredMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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

    public function test_xendit_expired_webhook_queues_notification_email(): void
    {
        Mail::fake();
        $this->mockGateway('expired', 'PAY-EXPIRE');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-EXPIRE');

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-EXPIRE', 'status' => 'EXPIRED'])
            ->assertStatus(200);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Expired->value]);
        Mail::assertQueued(PaymentExpiredMail::class, fn ($mail) => $mail->payment->id === $payment->id);
    }

    public function test_midtrans_expired_webhook_queues_notification_email(): void
    {
        Mail::fake();
        $this->mockGateway('expired', 'PAY-MIDTRANS-EXPIRE');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-MIDTRANS-EXPIRE');

        $this->postJson('/api/webhooks/midtrans', [
            'order_id'           => 'PAY-MIDTRANS-EXPIRE',
            'transaction_status' => 'expire',
            'status_code'        => '407',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'mock_verified',
        ])->assertStatus(200);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Expired->value]);
        Mail::assertQueued(PaymentExpiredMail::class, fn ($mail) => $mail->payment->id === $payment->id);
    }

    public function test_midtrans_cancelled_webhook_queues_notification_email(): void
    {
        Mail::fake();
        $this->mockGateway('expired', 'PAY-MIDTRANS-CANCEL');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-MIDTRANS-CANCEL');

        $this->postJson('/api/webhooks/midtrans', [
            'order_id'           => 'PAY-MIDTRANS-CANCEL',
            'transaction_status' => 'cancel',
            'status_code'        => '407',
            'gross_amount'       => '100000.00',
            'signature_key'      => 'mock_verified',
        ])->assertStatus(200);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Expired->value]);
        Mail::assertQueued(PaymentExpiredMail::class, fn ($mail) => $mail->payment->id === $payment->id);
    }

    public function test_expire_webhook_cancels_pending_order(): void
    {
        $this->mockGateway('expired', 'PAY-CANCEL-TEST');
        ['order' => $order] = $this->paymentWithOrder('PAY-CANCEL-TEST');

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-CANCEL-TEST', 'status' => 'EXPIRED'])
            ->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
    }

    public function test_expire_webhook_does_not_cancel_non_pending_order(): void
    {
        $this->mockGateway('expired', 'PAY-PAID-CANCEL');
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid, 'total' => 10000000]);
        $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'gateway_ref' => 'PAY-PAID-CANCEL']);

        // Paid order — PaymentFailed won't be dispatched (terminal state guard)
        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-PAID-CANCEL', 'status' => 'EXPIRED'])
            ->assertStatus(200);

        // Order must stay Paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Paid->value]);
    }

    // ── Recovery: paid webhook on locally-expired payment ────────────────────

    public function test_paid_webhook_recovers_locally_expired_payment_when_order_is_pending(): void
    {
        Event::fake();
        $this->mockGateway('paid', 'PAY-RECOVER');
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'total' => 10000000]);
        // Simulate scheduler having expired the payment locally (soft-terminal)
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'gateway_ref' => 'PAY-RECOVER',
            'status'      => PaymentStatus::Expired,
            'amount'      => 10000000,
        ]);

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-RECOVER', 'status' => 'PAID'])
            ->assertStatus(200);

        // Payment must be recovered to Paid
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Paid->value]);
        // Order must be fulfilled
        Event::assertDispatched(PaymentCaptured::class);
    }

    public function test_double_charge_webhook_auto_credits_user_wallet(): void
    {
        Event::fake();
        $this->mockGateway('paid', 'PAY-DOUBLEP');
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid, 'total' => 10000000]);
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'gateway_ref' => 'PAY-DOUBLEP',
            'status'      => PaymentStatus::Expired,
            'amount'      => 10000000,
        ]);

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-DOUBLEP', 'status' => 'PAID'])
            ->assertStatus(200);

        // No PaymentCaptured — order already paid
        Event::assertNotDispatched(PaymentCaptured::class);
        // Order stays Paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Paid->value]);
        // Refund record created for audit
        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id]);
        // User wallet credited with duplicate amount
        $this->assertDatabaseHas('wallet_transactions', [
            'type'         => 'credit',
            'amount'       => 10000000,
            'reference_id' => $payment->id,
        ]);
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
