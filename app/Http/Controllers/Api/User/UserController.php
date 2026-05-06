<?php

namespace App\Http\Controllers\Api\User;

use App\Contracts\Shared\MediaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SendPhoneOtpRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Http\Requests\User\VerifyPhoneRequest;
use App\Http\Resources\User\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly MediaServiceInterface $media,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success('Profile retrieved.', new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->validated());

        return ApiResponse::success('Profile updated.', new UserResource($user));
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $result = $this->media->generatePresignedUrl(
            'avatars',
            $request->input('filename'),
            $request->input('mime'),
        );

        return ApiResponse::success('Avatar presigned URL generated.', $result, 201);
    }

    public function confirmAvatar(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string', 'max:500']]);

        $key       = $request->input('key');
        $confirmed = $this->media->confirmUpload($key);

        if (! $confirmed) {
            return ApiResponse::error('Avatar file not found in storage.', 422);
        }

        $user = $this->userService->updateAvatar($request->user(), $key);

        return ApiResponse::success('Avatar updated.', new UserResource($user));
    }

    public function sendPhoneOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $this->userService->sendPhoneOtp(
            $request->user(),
            $request->input('phone'),
            $request,
        );

        return ApiResponse::success('OTP sent to the provided phone number.');
    }

    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $this->userService->verifyPhone(
            $request->user(),
            $request->input('phone'),
            $request->input('otp'),
        );

        return ApiResponse::success('Phone number verified.', new UserResource($request->user()->fresh()));
    }
}
