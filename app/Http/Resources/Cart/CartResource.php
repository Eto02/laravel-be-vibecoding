<?php

namespace App\Http\Resources\Cart;

use App\Enums\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->items->loadMissing(['variant', 'product.media', 'store']);

        $totalItems = $items->sum('quantity');
        $totalCents = $items->sum(fn ($i) => $i->price_snapshot * $i->quantity);

        $stores = $items
            ->groupBy('store_id')
            ->map(function ($storeItems, $storeId) {
                $store        = $storeItems->first()->store;
                $storeCents   = $storeItems->sum(fn ($i) => $i->price_snapshot * $i->quantity);

                return [
                    'store_id'            => $storeId,
                    'store_name'          => $store?->name,
                    'store_slug'          => $store?->slug,
                    'items'               => CartItemResource::collection($storeItems)->resolve(),
                    'store_subtotal_cents' => $storeCents,
                    'store_subtotal'      => 'Rp ' . number_format($storeCents / 100, 0, ',', '.'),
                ];
            })
            ->values();

        return [
            'total_items'        => $totalItems,
            'total_price_cents'  => $totalCents,
            'total_price'        => 'Rp ' . number_format($totalCents / 100, 0, ',', '.'),
            'stores'             => $stores,
        ];
    }
}
