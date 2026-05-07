<?php

namespace App\Http\Resources\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product     = $this->product;
        $isAvailable = $product && ! $product->trashed()
            && $product->status->value === 'active';

        return [
            'id'              => $this->id,
            'product_id'      => $this->product_id,
            'name'            => $product?->name,
            'slug'            => $product?->slug,
            'min_price_cents' => $product?->min_price,
            'min_price'       => $product ? 'Rp ' . number_format($product->min_price / 100, 0, ',', '.') : null,
            'status'          => $product?->status->value,
            'total_stock'     => $product?->total_stock,
            'is_available'    => $isAvailable,
            'thumbnail_url'   => $product?->media
                ?->firstWhere('is_primary', true)?->url,
            'added_at'        => $this->created_at?->toISOString(),
        ];
    }
}
