<?php

namespace App\Listeners\Merchant;

use App\Events\Merchant\StoreUnfollowed;
use Illuminate\Contracts\Queue\ShouldQueue;

class DecrementFollowerCount implements ShouldQueue
{
    public function handle(StoreUnfollowed $event): void
    {
        $event->store->decrement('follower_count');
    }
}
