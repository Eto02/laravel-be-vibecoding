<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\OAuthService;
use Illuminate\Http\JsonResponse;

class OAuthController extends Controller
{
    public function __construct(
        private readonly OAuthService $oauthService,
    ) {}

    public function redirect(string $provider): mixed
    {
        return $this->oauthService->redirectToProvider($provider);
    }

    public function callback(string $provider): JsonResponse
    {
        try {
            $result = $this->oauthService->handleCallback($provider);

            return ApiResponse::success('Login successful.', $result);
        } catch (\Exception $e) {
            return ApiResponse::error('OAuth authentication failed.', 401);
        }
    }
}
