<?php

namespace App\Listeners\Merchant;

use App\Events\Merchant\StoreFollowed;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncrementFollowerCount implements ShouldQueue
{
    public function handle(StoreFollowed $event): void
    {
        $event->store->increment('follower_count');
    }
}
