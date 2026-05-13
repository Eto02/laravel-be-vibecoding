<?php

namespace Tests\Unit\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PaymentServiceHandleWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_webhook_uses_gateway_ref_from_db_not_external_id_from_payload(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 10000000]);

        $transaction = Transaction::factory()->create([
            'external_id' => 'PAY-WEBHOOK-1',
            'amount'      => 10000000,
            'status'      => TransactionStatus::Pending,
        ]);

        $payment = Payment::factory()->create([
            'order_id'       => $order->id,
            'transaction_id' => $transaction->id,
            'gateway'        => 'xendit',
            'method'         => 'invoice',
            'gateway_ref'    => 'xendit-invoice-id-999',  // different from external_id
            'amount'         => 10000000,
            'status'         => PaymentStatus::Pending,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway->shouldReceive('parseWebhookPayload')
            ->once()
            ->andReturn([
                'event'       => 'payment.succeeded',
                'external_id' => 'PAY-WEBHOOK-1',
                'status'      => 'paid',
                'amount'      => 10000000,
            ]);

        // The critical assertion: getPaymentStatus must be called with gateway_ref (from DB),
        // NOT with external_id (from webhook payload).
        $gateway->shouldReceive('getPaymentStatus')
            ->once()
            ->with('xendit-invoice-id-999')
            ->andReturn(['status' => 'PAID', 'paid_amount' => 100000]);

        $gateway->shouldReceive('parseStatusResponse')
            ->once()
            ->andReturn(['status' => 'paid', 'amount' => 10000000]);

        app()->bind('payment.xendit', fn () => $gateway);

        $service = app(PaymentService::class);
        $request = Request::create('/webhook/xendit', 'POST');

        $service->handleWebhook($request, 'xendit');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_handle_webhook_skips_dual_verify_when_gateway_ref_is_null(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 10000000]);

        $transaction = Transaction::factory()->create([
            'external_id' => 'PAY-NO-REF-1',
            'amount'      => 10000000,
            'status'      => TransactionStatus::Pending,
        ]);

        $payment = Payment::factory()->create([
            'order_id'       => $order->id,
            'transaction_id' => $transaction->id,
            'gateway'        => 'xendit',
            'method'         => 'invoice',
            'gateway_ref'    => null,
            'amount'         => 10000000,
            'status'         => PaymentStatus::Pending,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway->shouldReceive('parseWebhookPayload')
            ->once()
            ->andReturn([
                'event'       => 'payment.succeeded',
                'external_id' => 'PAY-NO-REF-1',
                'status'      => 'paid',
                'amount'      => 10000000,
            ]);

        // getPaymentStatus must NOT be called when gateway_ref is null
        $gateway->shouldNotReceive('getPaymentStatus');
        $gateway->shouldNotReceive('parseStatusResponse');

        app()->bind('payment.xendit', fn () => $gateway);

        $service = app(PaymentService::class);
        $request = Request::create('/webhook/xendit', 'POST');

        $service->handleWebhook($request, 'xendit');

        // Status must remain pending — no state change without dual verification
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }
}
