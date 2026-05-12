<?php

namespace App\Listeners\Payment;

use App\Events\Payment\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyPaymentFailed implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;

        Log::warning('Payment failed or expired', [
            'payment_id' => $payment->id,
            'order_id'   => $payment->order_id,
            'gateway'    => $payment->gateway,
            'status'     => $payment->status->value,
        ]);
    }
}
