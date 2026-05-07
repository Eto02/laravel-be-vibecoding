<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'status'          => $this->status->value,
            'min_price_cents' => $this->min_price,
            'min_price'       => 'Rp ' . number_format($this->min_price / 100, 0, ',', '.'),
            'max_price_cents' => $this->max_price,
            'max_price'       => 'Rp ' . number_format($this->max_price / 100, 0, ',', '.'),
            'total_stock'     => $this->total_stock,
            'sold_count'      => $this->sold_count,
            'rating_avg'      => $this->rating_avg,
            'weight_gram'     => $this->weight_gram,
            'store'           => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ]),
            'category'        => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'variants'        => $this->whenLoaded('variants', fn () => VariantResource::collection($this->variants)),
            'media'           => $this->whenLoaded('media', fn () => ProductMediaResource::collection($this->media)),
            'is_wishlisted'   => $this->when(isset($this->is_wishlisted), $this->is_wishlisted),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
