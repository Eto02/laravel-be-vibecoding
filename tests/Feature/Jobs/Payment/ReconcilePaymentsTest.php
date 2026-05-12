<?php

namespace Tests\Feature\Jobs\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentCaptured;
use App\Events\Payment\PaymentFailed;
use App\Jobs\Payment\ReconcilePayments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReconcilePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function mockGatewayApiStatus(string $externalId, string $status): void
    {
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('getPaymentStatus')->willReturn(['status' => $status, 'amount' => 10000000]);
        $mock->method('parseStatusResponse')->willReturn(['status' => $status, 'amount' => 10000000]);

        $this->app->instance('payment.xendit', $mock);
        $this->app->instance('payment.midtrans', $mock);
    }

    private function pendingPaymentPastExpiry(string $gatewayRef, int $expiredMinutesAgo = 10): Payment
    {
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Pending, 'total' => 10000000]);

        return Payment::factory()->create([
            'order_id'    => $order->id,
            'gateway_ref' => $gatewayRef,
            'status'      => PaymentStatus::Pending,
            'amount'      => 10000000,
            'gateway'     => 'xendit',
            'expires_at'  => now()->subMinutes($expiredMinutesAgo),
        ]);
    }

    public function test_reconcile_marks_paid_payment_when_api_confirms_paid(): void
    {
        Event::fake();
        $payment = $this->pendingPaymentPastExpiry('PAY-RECONCILE-PAID');
        $this->mockGatewayApiStatus('PAY-RECONCILE-PAID', 'paid');

        (new ReconcilePayments())->handle(app(\App\Services\Payment\PaymentService::class));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Paid->value]);
        Event::assertDispatched(PaymentCaptured::class);
    }

    public function test_reconcile_marks_expired_payment_when_api_confirms_expired(): void
    {
        Event::fake();
        $payment = $this->pendingPaymentPastExpiry('PAY-RECONCILE-EXP');
        $this->mockGatewayApiStatus('PAY-RECONCILE-EXP', 'expired');

        (new ReconcilePayments())->handle(app(\App\Services\Payment\PaymentService::class));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Expired->value]);
        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_reconcile_does_not_process_payments_outside_grace_window(): void
    {
        Event::fake();
        // expired 60 minutes ago — past the 30-min Xendit grace, expireUnpaidPayments() handles these
        $payment = $this->pendingPaymentPastExpiry('PAY-OLD', 60);
        $this->mockGatewayApiStatus('PAY-OLD', 'expired');

        (new ReconcilePayments())->handle(app(\App\Services\Payment\PaymentService::class));

        // Payment should NOT be touched — outside the gap window
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Pending->value]);
    }

    public function test_reconcile_skips_already_paid_payment(): void
    {
        Event::fake();
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $order   = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Paid, 'total' => 10000000]);
        // Hard-terminal: already Paid — should never appear in the candidate query
        Payment::factory()->paid()->create([
            'order_id'    => $order->id,
            'gateway_ref' => 'PAY-ALREADY-PAID',
            'gateway'     => 'xendit',
            'expires_at'  => now()->subMinutes(5),
        ]);

        $this->mockGatewayApiStatus('PAY-ALREADY-PAID', 'paid');

        (new ReconcilePayments())->handle(app(\App\Services\Payment\PaymentService::class));

        // No duplicate event dispatched
        Event::assertNotDispatched(PaymentCaptured::class);
    }

    public function test_reconcile_continues_on_individual_gateway_failure(): void
    {
        Event::fake();

        $goodPayment = $this->pendingPaymentPastExpiry('PAY-GOOD', 10);
        $badPayment  = $this->pendingPaymentPastExpiry('PAY-BAD', 10);

        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('getPaymentStatus')->willReturnCallback(function (string $externalId) {
            if ($externalId === 'PAY-BAD') {
                throw new \RuntimeException('Gateway error');
            }
            return ['status' => 'expired', 'amount' => 10000000];
        });
        $mock->method('parseStatusResponse')->willReturn(['status' => 'expired', 'amount' => 10000000]);
        $this->app->instance('payment.xendit', $mock);
        $this->app->instance('payment.midtrans', $mock);

        // Should not throw — individual failures are caught and logged
        (new ReconcilePayments())->handle(app(\App\Services\Payment\PaymentService::class));

        // Good payment was processed
        $this->assertDatabaseHas('payments', ['id' => $goodPayment->id, 'status' => PaymentStatus::Expired->value]);
        // Bad payment stays pending
        $this->assertDatabaseHas('payments', ['id' => $badPayment->id, 'status' => PaymentStatus::Pending->value]);
    }
}
