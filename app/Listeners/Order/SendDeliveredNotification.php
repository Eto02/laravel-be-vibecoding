<?php

namespace App\Listeners\Order;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Order\OrderDelivered;
use App\Mail\Order\OrderDeliveredMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeliveredNotification implements ShouldQueue
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;
        $order->loadMissing('user', 'items');

        $this->email->send($order->user, new OrderDeliveredMail($order));
    }
}
