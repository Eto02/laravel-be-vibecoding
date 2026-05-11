<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'order_number'   => $this->order_number,
            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'store'          => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ]),
            'buyer'          => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'total_cents'    => $this->total,
            'total'          => 'Rp ' . number_format($this->total / 100, 0, ',', '.'),
            'item_count'     => $this->whenLoaded('items', fn () => $this->items->count()),
            'payment_due_at' => $this->payment_due_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
