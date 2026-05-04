<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->registerUser($request->validated());

        return ApiResponse::success('User registered successfully.', $result, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginUser($request->validated());

            return ApiResponse::success('Login successful.', $result);
        } catch (\Exception $e) {
            return ApiResponse::error('Invalid credentials.', 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logoutUser($request->user());

        return ApiResponse::success('Logged out successfully.');
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken($request->validated()['refresh_token']);

            return ApiResponse::success('Token refreshed successfully.', $result);
        } catch (\Exception $e) {
            return ApiResponse::error('Invalid or expired refresh token.', 401);
        }
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('User retrieved.', new UserResource($request->user()));
    }
}
