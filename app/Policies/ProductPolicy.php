<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        return $product->store->user_id === $user->id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $product->store->user_id === $user->id;
    }

    public function manageMedia(User $user, Product $product): bool
    {
        return $product->store->user_id === $user->id;
    }

    public function manageVariants(User $user, Product $product): bool
    {
        return $product->store->user_id === $user->id;
    }
}
