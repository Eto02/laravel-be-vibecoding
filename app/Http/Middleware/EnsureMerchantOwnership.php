<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->store()->exists()) {
            return ApiResponse::error('You do not have a store.', 403);
        }

        return $next($request);
    }
}
