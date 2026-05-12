<?php

namespace App\Listeners\Order;

use App\Enums\PaymentStatus;
use App\Events\Order\OrderCancelled;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessRefundIfPaid implements ShouldQueue
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        $paidPayment = Payment::where('order_id', $order->id)
            ->where('status', PaymentStatus::Paid)
            ->first();

        if (! $paidPayment) {
            return;
        }

        $this->paymentService->requestRefund($paidPayment, 'Order cancelled — auto-refund.');
    }
}
