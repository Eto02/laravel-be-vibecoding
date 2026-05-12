<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\Auth\TokenResource;
use App\Http\Resources\Auth\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->registerUser($request->validated());

        return ApiResponse::success('User registered successfully.', new TokenResource($result), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginUser($request->validated());

            return ApiResponse::success('Login successful.', new TokenResource($result));
        } catch (ValidationException $e) {
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

            return ApiResponse::success('Token refreshed successfully.', new TokenResource($result));
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid or expired refresh token.', 401);
        }
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('User retrieved.', new UserResource($request->user()));
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error('Invalid or expired verification link.', 403);
        }

        $verified = $this->authService->verifyEmail(
            (int) $request->query('id'),
            (string) $request->query('hash'),
        );

        if (! $verified) {
            return ApiResponse::error('Email verification failed.', 422);
        }

        return ApiResponse::success('Email verified successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        try {
            $this->authService->resendVerificationEmail($request->user());

            return ApiResponse::success('Verification email sent.');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->input('email'));

        return ApiResponse::success('If that email exists, a reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword($request->validated());

            return ApiResponse::success('Password reset successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error('Invalid or expired reset token.', 422);
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->changePassword(
                $request->user(),
                $request->input('current_password'),
                $request->input('password'),
            );

            return ApiResponse::success('Password changed successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = $this->authService->getSessions($request->user());

        return ApiResponse::success('Active sessions retrieved.', $sessions);
    }

    public function revokeSession(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        try {
            $this->authService->revokeSession($request->user(), $id);

            return response()->noContent();
        } catch (\Exception $e) {
            return ApiResponse::error('Session not found.', 404);
        }
    }
}
