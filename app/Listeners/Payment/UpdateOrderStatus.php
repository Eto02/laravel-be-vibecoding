<?php

namespace App\Listeners\Payment;

use App\Enums\OrderStatus;
use App\Events\Payment\PaymentCaptured;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateOrderStatus implements ShouldQueue
{
    public function handle(PaymentCaptured $event): void
    {
        $payment = $event->payment;
        $order   = $payment->order;

        if ($order && $order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Paid]);
        }
    }
}
