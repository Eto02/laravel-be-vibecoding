<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class RestoreProductStock implements ShouldQueue
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            DB::table('product_variants')
                ->where('id', $item->product_variant_id)
                ->increment('stock', $item->quantity);
        }
    }
}
