<?php

namespace App\Listeners\Order;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessRefundIfPaid implements ShouldQueue
{
    // Stub — full implementation in Sprint 7 when PaymentService is available.
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        if ($order->status !== OrderStatus::Cancelled) {
            return;
        }

        // Sprint 7: check if order was paid before cancellation and trigger refund via PaymentService.
        Log::info('ProcessRefundIfPaid: refund stub triggered', [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
        ]);
    }
}
