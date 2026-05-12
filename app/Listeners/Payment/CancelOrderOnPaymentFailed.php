<?php

namespace App\Listeners\Payment;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCancelled;
use App\Events\Payment\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;

class CancelOrderOnPaymentFailed implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $order = $event->payment->order;

        if (! $order || $order->status !== OrderStatus::Pending) {
            return;
        }

        $order->update(['status' => OrderStatus::Cancelled]);

        OrderCancelled::dispatch($order);
    }
}
