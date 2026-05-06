<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user'          => $this->resource['user'],
            'access_token'  => $this->resource['access_token'],
            'refresh_token' => $this->resource['refresh_token'],
            'token_type'    => $this->resource['token_type'],
            'expires_in'    => $this->resource['expires_in'],
            'provider'      => $this->resource['provider'],
        ];
    }
}
