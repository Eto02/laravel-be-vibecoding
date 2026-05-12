<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'balance_cents'   => $this->balance,
            'balance'         => 'Rp ' . number_format($this->balance / 100, 0, ',', '.'),
            'on_hold_cents'   => $this->on_hold,
            'on_hold'         => 'Rp ' . number_format($this->on_hold / 100, 0, ',', '.'),
            'available_cents' => $this->balance - $this->on_hold,
            'available'       => 'Rp ' . number_format(($this->balance - $this->on_hold) / 100, 0, ',', '.'),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
