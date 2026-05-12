<?php

namespace App\Services\Payment;

use App\DTOs\Payment\InitiatePaymentDTO;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TransactionStatus;
use App\Events\Payment\PaymentCaptured;
use App\Events\Payment\PaymentFailed;
use App\Events\Payment\RefundProcessed;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Transaction;
use App\Contracts\Shared\IdempotencyServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly IdempotencyServiceInterface $idempotency,
    ) {}

    public function initiatePayment(InitiatePaymentDTO $data): Payment
    {
        if ($data->idempotencyKey) {
            $result = $this->idempotency->check(
                "pay:init:{$data->idempotencyKey}",
                fn () => ['payment_id' => $this->createPayment($data)->id]
            );
            $payment = Payment::find($result['payment_id'] ?? null);
            if ($payment) {
                return $payment;
            }
        }

        return $this->createPayment($data);
    }

    public function switchPayment(Payment $currentPayment, InitiatePaymentDTO $newData): Payment
    {
        if ($currentPayment->status !== PaymentStatus::Pending) {
            throw new \DomainException('Can only switch a pending payment.');
        }

        // Cancel old charge on gateway (best-effort — don't fail if already expired)
        try {
            $oldGateway = app("payment.{$currentPayment->gateway}");
            $oldGateway->cancelCharge($currentPayment->gateway_ref, $currentPayment->method);
        } catch (\Throwable) {}

        $currentPayment->update(['status' => PaymentStatus::Expired]);
        $currentPayment->transaction?->update(['status' => TransactionStatus::Expired]);

        // Create new payment with the requested method (no idempotency key — switch is always fresh)
        return $this->createPayment($newData);
    }

    public function cancelPendingPaymentsForOrder(Order $order): void
    {
        $payments = Payment::where('order_id', $order->id)
            ->where('status', PaymentStatus::Pending)
            ->get();

        foreach ($payments as $payment) {
            try {
                $gateway = app("payment.{$payment->gateway}");
                $gateway->cancelCharge($payment->gateway_ref, $payment->method);
            } catch (\Throwable) {}

            $payment->update(['status' => PaymentStatus::Expired]);
            $payment->transaction?->update(['status' => TransactionStatus::Expired]);
        }
    }

    public function getStatus(Payment $payment): Payment
    {
        return $payment->load(['order', 'transaction']);
    }

    public function handleWebhook(Request $request, string $provider): void
    {
        $gateway = app("payment.{$provider}");

        if (! $gateway->verifyWebhook($request)) {
            throw new \DomainException('Invalid webhook signature.', 403);
        }

        $normalized  = $gateway->parseWebhookPayload($request);
        $externalId  = $normalized['external_id'];
        $status      = $normalized['status'];

        $payment = Payment::where('gateway_ref', $externalId)
            ->orWhereHas('transaction', fn ($q) => $q->where('external_id', $externalId))
            ->first();

        if (! $payment) {
            $transaction = Transaction::where('external_id', $externalId)->first();
            if ($transaction) {
                $payment = Payment::where('transaction_id', $transaction->id)->first();
            }
        }

        if (! $payment) {
            return; // Unknown payment — skip silently
        }

        // Skip if already in a terminal state
        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded])) {
            return;
        }

        match ($status) {
            'paid'    => $this->markPaid($payment, $normalized['amount']),
            'expired' => $this->markExpired($payment),
            'pending' => null, // informational callback — no state change
            default   => $this->markFailed($payment),
        };
    }

    public function requestRefund(Payment $payment, string $reason): Refund
    {
        if ($payment->status === PaymentStatus::Paid) {
            if ($payment->refund()->where('status', RefundStatus::Pending)->exists()) {
                throw new \DomainException('A refund is already pending for this payment.');
            }

            $gateway       = app("payment.{$payment->gateway}");
            $gatewayResult = $gateway->refundPayment($payment->gateway_ref, $payment->amount);

            $refund = Refund::create([
                'payment_id'  => $payment->id,
                'amount'      => $payment->amount,
                'reason'      => $reason,
                'status'      => RefundStatus::Processed,
                'gateway_ref' => $gatewayResult['id'] ?? $gatewayResult['refund_id'] ?? null,
                'refunded_at' => now(),
            ]);

            $payment->update(['status' => PaymentStatus::Refunded]);
            $payment->transaction?->update(['status' => TransactionStatus::Expired]);

            RefundProcessed::dispatch($refund);

            return $refund;
        }

        if (in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired])) {
            $refund = Refund::create([
                'payment_id'  => $payment->id,
                'amount'      => $payment->amount,
                'reason'      => $reason,
                'status'      => RefundStatus::Processed,
                'refunded_at' => now(),
            ]);

            $payment->update(['status' => PaymentStatus::Failed]);

            return $refund;
        }

        throw new \DomainException('Cannot refund a payment in status: ' . $payment->status->value);
    }

    public function expireUnpaidPayments(): int
    {
        $expired = Payment::where('status', PaymentStatus::Pending)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $payment) {
            $this->markExpired($payment);
        }

        return $expired->count();
    }

    private function createPayment(InitiatePaymentDTO $data): Payment
    {
        $order = Order::where('id', $data->orderId)
            ->where('status', OrderStatus::Pending)
            ->firstOrFail();

        // Guard: one active payment per order at a time
        $active = Payment::where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Paid])
            ->first();

        if ($active) {
            if ($active->status === PaymentStatus::Paid) {
                throw new \DomainException('This order has already been paid.');
            }
            return $active;
        }

        $externalId = 'PAY-' . strtoupper(Str::random(10)) . '-' . $order->id;

        $method = match ($data->gateway) {
            'midtrans' => 'snap',
            default    => 'invoice',
        };

        $chargeData = [
            'external_id'    => $externalId,
            'amount'         => $order->total,
            'expires_at'     => $order->payment_due_at?->toISOString(),
            'customer_name'  => $order->user->name,
            'customer_email' => $order->user->email,
            'description'    => "Payment for order {$order->order_number}",
        ];

        $gateway = app("payment.{$data->gateway}");
        $result  = $gateway->createCharge($chargeData);

        $transaction = Transaction::create([
            'external_id' => $externalId,
            'amount'      => $order->total,
            'status'      => TransactionStatus::Pending,
            'invoice_url' => $result['redirect_url'],
        ]);

        return Payment::create([
            'order_id'        => $order->id,
            'transaction_id'  => $transaction->id,
            'gateway'         => $data->gateway,
            'method'          => $method,
            'gateway_ref'     => $result['gateway_ref'],
            'amount'          => $order->total,
            'status'          => PaymentStatus::Pending,
            'payment_details' => $result['payment_details'],
            'expires_at'      => $result['expires_at'] ? now()->parse($result['expires_at']) : $order->payment_due_at,
        ]);
    }

    private function markPaid(Payment $payment, int $amount): void
    {
        $payment->update(['status' => PaymentStatus::Paid]);
        $payment->transaction?->update(['status' => TransactionStatus::Paid, 'paid_at' => now()]);
        PaymentCaptured::dispatch($payment->load('order'));
    }

    private function markExpired(Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Expired]);
        $payment->transaction?->update(['status' => TransactionStatus::Expired]);
        PaymentFailed::dispatch($payment->load('order'));
    }

    private function markFailed(Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Failed]);
        PaymentFailed::dispatch($payment->load('order'));
    }
}
