<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'sku'         => $this->sku,
            'price_cents' => $this->price,
            'price'       => 'Rp ' . number_format($this->price / 100, 0, ',', '.'),
            'stock'       => $this->stock,
            'weight_gram' => $this->weight_gram,
            'attributes'  => $this->attributes,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
