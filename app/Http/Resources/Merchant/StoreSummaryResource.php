<?php

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r2Url = rtrim(config('filesystems.disks.r2.url', ''), '/');

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'logo_url'       => $this->logo ? "{$r2Url}/{$this->logo}" : null,
            'city'           => $this->city,
            'province'       => $this->province,
            'rating_avg'     => (float) $this->rating_avg,
            'follower_count' => $this->follower_count,
        ];
    }
}
