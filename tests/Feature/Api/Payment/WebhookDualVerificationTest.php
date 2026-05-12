<?php

namespace Tests\Feature\Api\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Payment\PaymentCaptured;
use App\Events\Payment\PaymentFailed;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebhookDualVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function paymentWithOrder(string $gatewayRef = 'PAY-DVT'): array
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

    private function mockGatewayWithDivergence(string $webhookStatus, string $apiStatus, string $externalId): void
    {
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('verifyWebhook')->willReturn(true);
        $mock->method('parseWebhookPayload')->willReturn([
            'event'       => 'payment.' . $webhookStatus,
            'external_id' => $externalId,
            'status'      => $webhookStatus,
            'amount'      => 10000000,
        ]);
        // API says something different from the webhook — API wins
        $mock->method('getPaymentStatus')->willReturn(['status' => $apiStatus, 'amount' => 10000000]);
        $mock->method('parseStatusResponse')->willReturn(['status' => $apiStatus, 'amount' => 10000000]);

        $this->app->instance('payment.xendit', $mock);
    }

    private function mockGatewayWithApiTimeout(string $externalId): void
    {
        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('verifyWebhook')->willReturn(true);
        $mock->method('parseWebhookPayload')->willReturn([
            'event'       => 'payment.paid',
            'external_id' => $externalId,
            'status'      => 'paid',
            'amount'      => 10000000,
        ]);
        // Gateway API call throws — simulates timeout or gateway downtime
        $mock->method('getPaymentStatus')->willThrowException(new \RuntimeException('Gateway timeout'));

        $this->app->instance('payment.xendit', $mock);
    }

    public function test_api_status_overrides_webhook_status_when_they_diverge(): void
    {
        Event::fake();
        // Webhook says "paid" but API says "pending" — no state change should happen
        $this->mockGatewayWithDivergence('paid', 'pending', 'PAY-DIVERGE');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-DIVERGE');

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-DIVERGE', 'status' => 'PAID'])
            ->assertStatus(200);

        // API returned "pending" — payment must remain Pending, no event
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Pending->value]);
        Event::assertNotDispatched(PaymentCaptured::class);
    }

    public function test_webhook_paid_with_api_expired_marks_payment_expired(): void
    {
        Event::fake();
        // Webhook claims paid but API confirms expired — follow the API
        $this->mockGatewayWithDivergence('paid', 'expired', 'PAY-GHOST');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-GHOST');

        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-GHOST', 'status' => 'PAID'])
            ->assertStatus(200);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Expired->value]);
        Event::assertDispatched(PaymentFailed::class);
        Event::assertNotDispatched(PaymentCaptured::class);
    }

    public function test_api_timeout_does_not_change_payment_state(): void
    {
        Event::fake();
        $this->mockGatewayWithApiTimeout('PAY-TIMEOUT');
        ['payment' => $payment] = $this->paymentWithOrder('PAY-TIMEOUT');

        // With QUEUE_CONNECTION=sync, the job runs inline. A gateway timeout causes
        // the job to throw, which the HTTP layer converts to a 500 response.
        // The important thing: payment state must NOT change on an API failure.
        $this->postJson('/api/webhooks/xendit', ['external_id' => 'PAY-TIMEOUT', 'status' => 'PAID'])
            ->assertStatus(500);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Pending->value]);
        Event::assertNotDispatched(PaymentCaptured::class);
    }
}
