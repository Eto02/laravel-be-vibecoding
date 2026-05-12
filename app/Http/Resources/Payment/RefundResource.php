<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'payment_id'   => $this->payment_id,
            'amount_cents' => $this->amount,
            'amount'       => 'Rp ' . number_format($this->amount / 100, 0, ',', '.'),
            'reason'       => $this->reason,
            'status'       => $this->status->value,
            'gateway_ref'  => $this->gateway_ref,
            'refunded_at'  => $this->refunded_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
