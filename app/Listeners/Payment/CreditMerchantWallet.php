<?php

namespace App\Listeners\Payment;

use App\Events\Order\OrderCompleted;
use App\Services\Payment\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreditMerchantWallet implements ShouldQueue
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function handle(OrderCompleted $event): void
    {
        $this->walletService->creditMerchant($event->order->load('store.user'));
    }
}
