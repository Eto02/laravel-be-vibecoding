<?php

namespace App\Listeners\Order;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Order\OrderShipped;
use App\Mail\Order\OrderShippedMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendShippingNotification implements ShouldQueue
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(OrderShipped $event): void
    {
        $order = $event->order;
        $order->loadMissing('user', 'items');

        $this->email->send($order->user, new OrderShippedMail($order));
    }
}
