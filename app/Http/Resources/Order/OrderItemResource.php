<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snap = $this->product_snapshot ?? [];

        return [
            'id'                 => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'product_name'       => $snap['product_name'] ?? null,
            'variant_sku'        => $snap['variant_sku'] ?? null,
            'attributes'         => $snap['attributes'] ?? null,
            'thumbnail_url'      => $snap['thumbnail_url'] ?? null,
            'quantity'           => $this->quantity,
            'unit_price_cents'   => $this->unit_price,
            'unit_price'         => 'Rp ' . number_format($this->unit_price / 100, 0, ',', '.'),
            'subtotal_cents'     => $this->subtotal,
            'subtotal'           => 'Rp ' . number_format($this->subtotal / 100, 0, ',', '.'),
        ];
    }
}
