<?php

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreFollowerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id'     => $this->user_id,
            'name'        => $this->user?->name,
            'avatar_url'  => $this->user?->avatar
                ? rtrim(config('filesystems.disks.r2.url'), '/') . '/' . $this->user->avatar
                : null,
            'followed_at' => $this->created_at?->toISOString(),
        ];
    }
}
