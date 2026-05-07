<?php

namespace App\Http\Resources\Product;

use App\Services\Shared\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'url'        => app(MediaService::class)->publicUrl($this->file),
            'type'       => $this->type,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
