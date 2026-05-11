<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CancelExpiredOrderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(OrderService $orderService): void
    {
        // Re-fetch to get the latest status — the model state at dispatch time may be stale
        $order = Order::find($this->order->id);

        if (! $order || ! $order->isPending()) {
            return;
        }

        $orderService->cancel($order, null, 'Auto-cancelled: payment deadline passed.');
    }
}
