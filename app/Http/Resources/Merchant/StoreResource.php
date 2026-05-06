<?php

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r2Url = rtrim(config('filesystems.disks.r2.url', ''), '/');

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'logo_url'       => $this->logo   ? "{$r2Url}/{$this->logo}"   : null,
            'banner_url'     => $this->banner  ? "{$r2Url}/{$this->banner}" : null,
            'status'         => $this->status->value,
            'kyc_status'     => $this->kyc_status->value,
            'city'           => $this->city,
            'province'       => $this->province,
            'phone'          => $this->phone,
            'rating_avg'     => (float) $this->rating_avg,
            'total_sales'    => $this->total_sales,
            'follower_count' => $this->follower_count,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
