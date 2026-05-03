<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'external_id' => $this->external_id,
            'amount'      => $this->amount,
            'status'      => $this->status->value,
            'invoice_url' => $this->invoice_url,
            'paid_at'     => $this->paid_at?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
