<?php

namespace App\Services\Payment;

use App\Contracts\Shared\IdempotencyServiceInterface;
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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly IdempotencyServiceInterface $idempotency,
        private readonly WalletService $wallet,
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
        } catch (\Throwable $e) {
            Log::warning('Failed to cancel old gateway charge on payment switch', [
                'payment_id'  => $currentPayment->id,
                'gateway_ref' => $currentPayment->gateway_ref,
                'gateway'     => $currentPayment->gateway,
                'error'       => $e->getMessage(),
            ]);
        }

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

    public function findForUser(User $user, int $id): Payment
    {
        return Payment::where('id', $id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();
    }

    public function getStatus(Payment $payment): Payment
    {
        return $payment->load(['order', 'transaction']);
    }

    public function handleWebhook(Request $request, string $provider): void
    {
        $gateway = app("payment.{$provider}");

        // Signature verification is done in WebhookController before dispatching the job
        $normalized = $gateway->parseWebhookPayload($request);
        $externalId = $normalized['external_id'];

        $payment = $this->findPaymentByExternalId($externalId);
        if (! $payment) {
            return;
        }

        // Guard: gateway_ref null means no valid gateway reference stored — do not fall back to
        // external_id because that would reintroduce the exact bug we're fixing here.
        if (! $payment->gateway_ref) {
            Log::warning('handleWebhook: gateway_ref null, skipping dual-verify', [
                'payment_id'  => $payment->id,
                'external_id' => $externalId,
                'provider'    => $provider,
            ]);
            return;
        }

        // Dual verification: webhook payload is a signal, not the truth.
        // Use gateway_ref from DB (stored at createCharge time) — not external_id from webhook.
        $apiResponse = $gateway->getPaymentStatus($payment->gateway_ref);
        $verified    = $gateway->parseStatusResponse($apiResponse);

        $this->applyStatusTransition($payment, $verified['status'], $verified['amount']);
    }

    public function handleApiStatusUpdate(Payment $payment): void
    {
        $gateway    = app("payment.{$payment->gateway}");
        $externalId = $payment->gateway_ref ?? $payment->transaction?->external_id;

        if (! $externalId) {
            return;
        }

        $apiResponse = $gateway->getPaymentStatus($externalId);
        $verified    = $gateway->parseStatusResponse($apiResponse);

        $this->applyStatusTransition($payment, $verified['status'], $verified['amount']);
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
        $graceConfig = config('payment.expiry_grace_minutes', []);
        $defaultGrace = 30;

        $expired = Payment::where('status', PaymentStatus::Pending)
            ->where(function ($query) use ($graceConfig, $defaultGrace) {
                foreach ($graceConfig as $gateway => $graceMinutes) {
                    $query->orWhere(function ($q) use ($gateway, $graceMinutes) {
                        $q->where('gateway', $gateway)
                          ->where('expires_at', '<', now()->subMinutes($graceMinutes));
                    });
                }
                // Fallback: any gateway not explicitly configured uses default grace
                $knownGateways = array_keys($graceConfig);
                if (! empty($knownGateways)) {
                    $query->orWhere(function ($q) use ($knownGateways, $defaultGrace) {
                        $q->whereNotIn('gateway', $knownGateways)
                          ->where('expires_at', '<', now()->subMinutes($defaultGrace));
                    });
                }
            })
            ->get();

        foreach ($expired as $payment) {
            $this->markExpired($payment);
        }

        return $expired->count();
    }

    private function applyStatusTransition(Payment $payment, string $status, int $amount): void
    {
        DB::transaction(function () use ($payment, $status, $amount) {
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            // Hard-terminal: Paid and Refunded are truly final — no state change ever
            if (in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::Refunded])) {
                return;
            }

            // Soft-terminal recovery: locally expired/failed but gateway says paid
            // Happens when scheduler fires before gateway session ends, or cancelCharge failed on switch
            if (in_array($locked->status, [PaymentStatus::Expired, PaymentStatus::Failed]) && $status === 'paid') {
                $order = $locked->order;

                if ($order && $order->status === OrderStatus::Pending) {
                    Log::warning('Paid signal for locally-expired payment — order still pending, recovering', [
                        'payment_id'   => $locked->id,
                        'order_id'     => $order->id,
                        'gateway'      => $locked->gateway,
                        'local_status' => $locked->status->value,
                    ]);
                    $this->cancelPendingPaymentsForOrder($order);
                    $this->markPaid($locked, $amount);
                    return;
                }

                // Order already fulfilled — auto-credit wallet, money must not be lost
                $this->refundDoubleChargeToWallet($locked, $amount, $locked->gateway_ref ?? '');
                return;
            }

            match ($status) {
                'paid'    => $this->markPaid($locked, $amount),
                'expired' => $this->markExpired($locked),
                'pending' => null,
                default   => $this->markFailed($locked),
            };
        });
    }

    private function findPaymentByExternalId(string $externalId): ?Payment
    {
        $payment = Payment::where('gateway_ref', $externalId)
            ->orWhereHas('transaction', fn ($q) => $q->where('external_id', $externalId))
            ->first();

        if (! $payment) {
            $transaction = Transaction::where('external_id', $externalId)->first();
            if ($transaction) {
                $payment = Payment::where('transaction_id', $transaction->id)->first();
            }
        }

        return $payment;
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

        // Payment session expires in PAYMENT_EXPIRY_MINUTES — capped by order's payment_due_at
        $expiryMinutes  = config('payment.expiry_minutes', 15);
        $paymentExpiresAt = now()->addMinutes($expiryMinutes);
        if ($order->payment_due_at && $order->payment_due_at->lt($paymentExpiresAt)) {
            $paymentExpiresAt = $order->payment_due_at;
        }

        $chargeData = [
            'external_id'    => $externalId,
            'amount'         => $order->total,
            'expires_at'     => $paymentExpiresAt->toISOString(),
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
            'expires_at'      => $result['expires_at'] ? now()->parse($result['expires_at']) : $paymentExpiresAt,
        ]);
    }

    private function refundDoubleChargeToWallet(Payment $payment, int $amount, string $gatewayRef): void
    {
        $user = $payment->order?->user;

        if (! $user) {
            Log::critical('Double payment detected but could not resolve user — manual refund required', [
                'payment_id'  => $payment->id,
                'gateway_ref' => $gatewayRef,
                'gateway'     => $payment->gateway,
                'amount'      => $amount,
            ]);
            return;
        }

        Refund::create([
            'payment_id'  => $payment->id,
            'amount'      => $amount,
            'reason'      => "Duplicate payment auto-refunded to wallet (gateway ref: {$gatewayRef})",
            'status'      => RefundStatus::Processed,
            'refunded_at' => now(),
        ]);

        $this->wallet->creditUser(
            $user,
            $amount,
            "Duplicate payment refunded to wallet (ref: {$gatewayRef})",
            Payment::class,
            $payment->id,
        );

        Log::critical('Double payment detected — auto-credited user wallet', [
            'payment_id'  => $payment->id,
            'order_id'    => $payment->order_id,
            'user_id'     => $user->id,
            'gateway_ref' => $gatewayRef,
            'gateway'     => $payment->gateway,
            'amount'      => $amount,
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
