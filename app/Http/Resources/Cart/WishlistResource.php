<?php

namespace App\Http\Resources\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->items->count(),
            'items' => WishlistItemResource::collection($this->items),
        ];
    }
}
