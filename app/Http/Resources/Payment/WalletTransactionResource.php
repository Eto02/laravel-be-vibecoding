<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'amount_cents'   => $this->amount,
            'amount'         => 'Rp ' . number_format($this->amount / 100, 0, ',', '.'),
            'description'    => $this->description,
            'reference_type' => $this->reference_type,
            'reference_id'   => $this->reference_id,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
