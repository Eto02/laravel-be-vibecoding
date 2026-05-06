<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'phone'             => $this->phone,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'avatar_url'        => $this->avatar
                                    ? rtrim(config('filesystems.disks.r2.url'), '/') . '/' . $this->avatar
                                    : null,
            'bio'               => $this->bio,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
