<?php

namespace App\Http\Resources\Product;

use App\Services\Shared\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryMedia = $this->media->where('is_primary', true)->first();

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'status'       => $this->status->value,
            'min_price_cents' => $this->min_price,
            'min_price'    => 'Rp ' . number_format($this->min_price / 100, 0, ',', '.'),
            'max_price_cents' => $this->max_price,
            'max_price'    => 'Rp ' . number_format($this->max_price / 100, 0, ',', '.'),
            'total_stock'  => $this->total_stock,
            'sold_count'   => $this->sold_count,
            'rating_avg'   => $this->rating_avg,
            'thumbnail'    => $primaryMedia ? app(MediaService::class)->publicUrl($primaryMedia->file) : null,
            'store'        => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ]),
            'category'     => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
