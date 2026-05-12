<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'order_id'        => $this->order_id,
            'gateway'         => $this->gateway,
            'method'          => $this->method,
            'amount_cents'    => $this->amount,
            'amount'          => 'Rp ' . number_format($this->amount / 100, 0, ',', '.'),
            'status'          => $this->status->value,
            'payment_details' => $this->payment_details,
            'redirect_url'    => $this->payment_details['redirect_url'] ?? $this->payment_details['checkout_url'] ?? null,
            'expires_at'      => $this->expires_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
