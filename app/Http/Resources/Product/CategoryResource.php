<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'icon'       => $this->icon,
            'level'      => $this->level,
            'sort_order' => $this->sort_order,
            'parent_id'  => $this->parent_id,
            'children'   => static::collection($this->whenLoaded('children')),
        ];
    }
}
