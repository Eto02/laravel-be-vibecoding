<?php

namespace App\Http\Resources\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant  = $this->whenLoaded('variant');
        $product  = $this->whenLoaded('product');
        $isAvailable = $this->resource->product
            && ! $this->resource->product->trashed()
            && $this->resource->product->status->value === 'active';

        $subtotal = $this->price_snapshot * $this->quantity;

        return [
            'id'                   => $this->id,
            'product_id'           => $this->product_id,
            'product_name'         => $this->resource->product?->name,
            'product_slug'         => $this->resource->product?->slug,
            'variant_id'           => $this->product_variant_id,
            'variant_sku'          => $this->resource->variant?->sku,
            'variant_attributes'   => $this->resource->variant?->attributes,
            'quantity'             => $this->quantity,
            'price_snapshot_cents' => $this->price_snapshot,
            'price_snapshot'       => 'Rp ' . number_format($this->price_snapshot / 100, 0, ',', '.'),
            'subtotal_cents'       => $subtotal,
            'subtotal'             => 'Rp ' . number_format($subtotal / 100, 0, ',', '.'),
            'current_stock'        => $this->resource->variant?->stock,
            'is_available'         => $isAvailable,
            'unavailable_reason'   => $isAvailable ? null : 'Produk tidak tersedia',
            'thumbnail_url'        => $this->resource->product?->media
                ?->firstWhere('is_primary', true)?->url,
        ];
    }
}
