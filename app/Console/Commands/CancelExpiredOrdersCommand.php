<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Console\Command;

class CancelExpiredOrdersCommand extends Command
{
    protected $signature   = 'orders:cancel-expired';
    protected $description = 'Cancel all pending orders whose payment deadline has passed';

    public function handle(OrderService $orderService): void
    {
        $count = 0;

        Order::pending()
            ->where('payment_due_at', '<', now())
            ->each(function (Order $order) use ($orderService, &$count) {
                $orderService->cancel($order, null, 'Auto-cancelled: payment deadline passed.');
                $count++;
            });

        $this->info("Cancelled {$count} expired order(s).");
    }
}
