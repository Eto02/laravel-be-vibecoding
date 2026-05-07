<?php

namespace App\Services\Cart;

use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Cart\AddCartItemDTO;
use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;

class CartService
{
    public function __construct(
        private readonly CacheServiceInterface $cache,
    ) {}

    public function getForUser(User $user): Cart
    {
        $cacheKey = "cart:user:{$user->id}";

        // Cache only the cart ID (integer) — never cache model objects to avoid unserialize failures
        $cartId = $this->cache->remember($cacheKey, 86400, function () use ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id])->id;
        });

        $cart = Cart::findOrFail($cartId);
        $cart->load([
            'items.variant',
            'items.product' => fn ($q) => $q->withTrashed(),
            'items.store:id,name,slug',
        ]);

        return $cart;
    }

    public function add(User $user, AddCartItemDTO $data): Cart
    {
        $variant = ProductVariant::with('product')->findOrFail($data->variantId);

        if ($variant->product->status !== ProductStatus::Active) {
            throw new \DomainException('Only active products can be added to cart.');
        }

        if ($data->quantity > $variant->stock) {
            throw new \DomainException("Stok tersedia: {$variant->stock}");
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $existing = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($existing) {
            $newQty = $existing->quantity + $data->quantity;
            if ($newQty > $variant->stock) {
                throw new \DomainException("Stok tersedia: {$variant->stock}");
            }
            $existing->update(['quantity' => $newQty]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $variant->id,
                'product_id'         => $variant->product_id,
                'store_id'           => $variant->product->store_id,
                'quantity'           => $data->quantity,
                'price_snapshot'     => $variant->price,
            ]);
        }

        $this->invalidateCache($user->id);

        return $this->getForUser($user);
    }

    public function update(User $user, CartItem $item, int $quantity): Cart
    {
        $this->authorizeItem($user, $item);

        $variant = $item->variant;
        if ($quantity > $variant->stock) {
            throw new \DomainException("Stok tersedia: {$variant->stock}");
        }

        $item->update(['quantity' => $quantity]);
        $this->invalidateCache($user->id);

        return $this->getForUser($user);
    }

    public function remove(User $user, CartItem $item): Cart
    {
        $this->authorizeItem($user, $item);
        $item->delete();
        $this->invalidateCache($user->id);

        return $this->getForUser($user);
    }

    public function clear(User $user): void
    {
        $cart = Cart::where('user_id', $user->id)->first();
        $cart?->items()->delete();
        $this->invalidateCache($user->id);
    }

    public function groupByStore(Cart $cart): array
    {
        $groups = [];

        foreach ($cart->items as $item) {
            $isAvailable = $item->product && ! $item->product->trashed()
                && $item->product->status === ProductStatus::Active;

            $storeId = $item->store_id;

            if (! isset($groups[$storeId])) {
                $groups[$storeId] = [
                    'store' => $item->store,
                    'items' => [],
                ];
            }

            $groups[$storeId]['items'][] = [
                'item'         => $item,
                'is_available' => $isAvailable,
            ];
        }

        return $groups;
    }

    private function authorizeItem(User $user, CartItem $item): void
    {
        $cart = Cart::where('user_id', $user->id)->first();
        if (! $cart || $item->cart_id !== $cart->id) {
            throw new \DomainException('Cart item does not belong to this user.');
        }
    }

    private function invalidateCache(int $userId): void
    {
        $this->cache->forget("cart:user:{$userId}");
    }
}
