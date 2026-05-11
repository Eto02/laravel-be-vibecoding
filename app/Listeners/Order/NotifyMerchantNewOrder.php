<?php

namespace App\Listeners\Order;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Order\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantNewOrder implements ShouldQueue
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $order->loadMissing('store.user', 'items');

        $merchantUser = $order->store->user;

        $this->email->sendRaw(
            $merchantUser->email,
            "New Order #{$order->order_number}",
            "You have a new order #{$order->order_number} with {$order->items->count()} item(s). Total: Rp " . number_format($order->total / 100, 0, ',', '.'),
        );
    }
}
