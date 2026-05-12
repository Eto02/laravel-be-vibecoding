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
        // Idempotency: if key provided, check Redis for existing payment ID
        if ($data->idempotencyKey) {
            $cached = $this->idempotency->check(
                $data->idempotencyKey,
                fn() => null // placeholder — we'll store the ID after creation
            );
            if (is_array($cached) && isset($cached['payment_id'])) {
                $existing = Payment::find($cached['payment_id']);
                if ($existing) {
                    return $existing;
                }
            }
        }

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
            return $active; // Return existing pending payment — no new gateway charge
        }

        $externalId = 'PAY-' . strtoupper(Str::random(10)) . '-' . $order->id;

        $chargeData = [
            'external_id'          => $externalId,
            'amount'               => $order->total,
            'method'               => $data->method,
            'bank_code'            => $data->bankCode,
            'ewallet_type'         => $data->ewalletType,
            'phone'                => $data->phone,
            'success_redirect_url' => $data->successRedirectUrl,
            'expires_at'           => $order->payment_due_at?->toISOString(),
            'customer_name'        => $order->user->name,
            'customer_email'       => $order->user->email,
        ];

        $result = $this->gateway->createCharge($chargeData);

        // Gateway log (Transaction)
        $transaction = Transaction::create([
            'external_id' => $externalId,
            'amount'      => $order->total,
            'status'      => TransactionStatus::Pending,
            'invoice_url' => $result['redirect_url'],
        ]);

        // Domain payment record
        $payment = Payment::create([
            'order_id'        => $order->id,
            'transaction_id'  => $transaction->id,
            'gateway'         => $data->gateway,
            'method'          => $data->method,
            'gateway_ref'     => $result['gateway_ref'],
            'amount'          => $order->total,
            'status'          => PaymentStatus::Pending,
            'payment_details' => $result['payment_details'],
            'expires_at'      => $result['expires_at'] ? now()->parse($result['expires_at']) : $order->payment_due_at,
        ]);

        // Store payment ID in idempotency cache for future dedup
        if ($data->idempotencyKey) {
            $this->idempotency->check(
                $data->idempotencyKey . ':stored',
                fn() => ['payment_id' => $payment->id]
            );
        }

        return $payment;
    }

    public function getStatus(Payment $payment): Payment
    {
        return $payment->load(['order', 'transaction']);
    }

    public function handleWebhook(Request $request, string $provider): void
    {
        if (! $this->gateway->verifyWebhook($request)) {
            throw new \DomainException('Invalid webhook signature.', 403);
        }

        $normalized  = $this->gateway->parseWebhookPayload($request);
        $externalId  = $normalized['external_id'];
        $status      = $normalized['status'];

        // Idempotency — skip if gateway_ref already processed
        $payment = Payment::where('gateway_ref', $externalId)
            ->orWhereHas('transaction', fn ($q) => $q->where('external_id', $externalId))
            ->first();

        if (! $payment) {
            // Try transaction lookup fallback
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
            'pending' => null, // informational callback (e.g. VA registered) — no state change
            default   => $this->markFailed($payment),
        };
    }

    public function requestRefund(Payment $payment, string $reason): Refund
    {
        if ($payment->status === PaymentStatus::Paid) {
            // Existing pending refund guard
            if ($payment->refund()->where('status', RefundStatus::Pending)->exists()) {
                throw new \DomainException('A refund is already pending for this payment.');
            }

            $gatewayResult = $this->gateway->refundPayment($payment->gateway_ref, $payment->amount);

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

        // Not yet paid — just cancel
        if (in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired])) {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'amount'     => $payment->amount,
                'reason'     => $reason,
                'status'     => RefundStatus::Processed,
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
