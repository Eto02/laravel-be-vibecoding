<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;

class WishlistService
{
    public function getForUser(User $user): Wishlist
    {
        $wishlist = Wishlist::firstOrCreate(['user_id' => $user->id]);
        $wishlist->load(['items.product.media' => fn ($q) => $q->where('is_primary', true)]);
        return $wishlist;
    }

    public function add(User $user, int $productId): Wishlist
    {
        $product = Product::findOrFail($productId);

        $wishlist = Wishlist::firstOrCreate(['user_id' => $user->id]);

        $exists = $wishlist->items()->where('product_id', $product->id)->exists();
        if ($exists) {
            throw new \DomainException('Product is already in your wishlist.');
        }

        $wishlist->items()->create(['product_id' => $product->id]);

        return $this->getForUser($user);
    }

    public function remove(User $user, int $productId): void
    {
        $wishlist = Wishlist::where('user_id', $user->id)->first();

        if (! $wishlist) {
            return;
        }

        $wishlist->items()->where('product_id', $productId)->delete();
    }

    public function check(User $user, int $productId): bool
    {
        $wishlist = Wishlist::where('user_id', $user->id)->first();

        if (! $wishlist) {
            return false;
        }

        return $wishlist->items()->where('product_id', $productId)->exists();
    }
}
