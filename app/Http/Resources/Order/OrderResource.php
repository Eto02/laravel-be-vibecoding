<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status->value,
            'status_label'     => $this->status->label(),
            'store'            => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ]),
            'buyer'            => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'address'          => $this->address_snapshot,
            'shipping_courier' => $this->shipping_courier,
            'shipping_service' => $this->shipping_service,
            'tracking_number'  => $this->tracking_number,
            'notes'            => $this->notes,
            'subtotal_cents'     => $this->subtotal,
            'subtotal'           => 'Rp ' . number_format($this->subtotal / 100, 0, ',', '.'),
            'shipping_fee_cents' => $this->shipping_fee,
            'shipping_fee'       => 'Rp ' . number_format($this->shipping_fee / 100, 0, ',', '.'),
            'discount_cents'     => $this->discount,
            'discount'           => 'Rp ' . number_format($this->discount / 100, 0, ',', '.'),
            'total_cents'        => $this->total,
            'total'              => 'Rp ' . number_format($this->total / 100, 0, ',', '.'),
            'payment_due_at'   => $this->payment_due_at?->toISOString(),
            'items'            => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'status_logs'      => $this->whenLoaded('statusLogs', fn () => $this->statusLogs->map(fn ($log) => [
                'from' => $log->from_status,
                'to'   => $log->to_status,
                'note' => $log->note,
                'at'   => $log->created_at?->toISOString(),
            ])),
            'dispute'          => $this->whenLoaded('dispute', fn () => $this->dispute
                ? new OrderDisputeResource($this->dispute)
                : null
            ),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
