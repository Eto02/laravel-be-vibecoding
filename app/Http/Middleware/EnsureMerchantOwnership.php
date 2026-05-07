<?php

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->store;

        if (! $store) {
            return ApiResponse::error('You do not have a store.', 403);
        }

        if (in_array($store->status, [MerchantStatus::Suspended, MerchantStatus::Banned])) {
            return ApiResponse::error('Your store has been suspended.', 403);
        }

        return $next($request);
    }
}
