<?php

namespace App\Events\Merchant;

use App\Models\Store;
use Illuminate\Foundation\Events\Dispatchable;

class StoreFollowed
{
    use Dispatchable;

    public function __construct(
        public readonly Store $store,
    ) {}
}
