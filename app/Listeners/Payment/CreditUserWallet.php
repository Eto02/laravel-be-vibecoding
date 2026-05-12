<?php

namespace App\Listeners\Payment;

use App\Events\Payment\RefundProcessed;
use App\Services\Payment\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreditUserWallet implements ShouldQueue
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function handle(RefundProcessed $event): void
    {
        $refund  = $event->refund;
        $payment = $refund->payment->load('order.user');
        $user    = $payment->order?->user;

        if (! $user) {
            return;
        }

        $this->walletService->creditUser(
            $user,
            $refund->amount,
            "Refund for order #{$payment->order->order_number}",
            get_class($refund),
            $refund->id,
        );
    }
}
