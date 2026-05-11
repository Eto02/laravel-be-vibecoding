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

        // Check status logs to see if order was ever paid before cancellation.
        // Sprint 7: trigger refund via PaymentService when confirmed paid.
        $wasPaid = $order->statusLogs()
            ->where('to_status', OrderStatus::Paid->value)
            ->exists();

        if (! $wasPaid) {
            return;
        }

        Log::info('ProcessRefundIfPaid: order was paid — refund pending Sprint 7 implementation', [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
        ]);
    }
}
