<?php

namespace App\Listeners\Payment;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Payment\PaymentFailed;
use App\Mail\Payment\PaymentExpiredMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyPaymentFailed implements ShouldQueue
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;
        $user    = $payment->order?->user;

        Log::warning('Payment failed or expired', [
            'payment_id' => $payment->id,
            'order_id'   => $payment->order_id,
            'gateway'    => $payment->gateway,
            'status'     => $payment->status->value,
        ]);

        if (! $user) {
            return;
        }

        $this->email->send($user, new PaymentExpiredMail($payment));
    }
}
