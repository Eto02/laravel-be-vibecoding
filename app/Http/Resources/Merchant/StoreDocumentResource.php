<?php

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'status'      => $this->status,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
