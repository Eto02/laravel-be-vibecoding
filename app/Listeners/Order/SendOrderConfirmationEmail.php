<?php

namespace App\Listeners\Order;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Order\OrderPlaced;
use App\Mail\Order\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $order->loadMissing('user', 'items');

        $this->email->send($order->user, new OrderConfirmationMail($order));
    }
}
