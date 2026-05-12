<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\ConfirmStoreMediaRequest;
use App\Http\Requests\Merchant\UpdateStoreRequest;
use App\Http\Requests\Merchant\UploadStoreMediaRequest;
use App\Http\Resources\Merchant\StoreResource;
use App\Http\Responses\ApiResponse;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchant,
    ) {}

    public function update(UpdateStoreRequest $request): JsonResponse
    {
        $store   = $this->merchant->getStoreForUser($request->user());
        $updated = $this->merchant->update($store, $request->validated());

        return ApiResponse::success('Store updated successfully.', new StoreResource($updated));
    }

    // ── Logo ─────────────────────────────────────────────────────────────────

    public function uploadLogo(UploadStoreMediaRequest $request): JsonResponse
    {
        $store  = $this->merchant->getStoreForUser($request->user());
        $result = $this->merchant->generateLogoPresignedUrl(
            $store,
            $request->input('filename'),
            $request->input('mime'),
        );

        return ApiResponse::success('Logo presigned URL generated.', $result, 201);
    }

    public function confirmLogo(ConfirmStoreMediaRequest $request): JsonResponse
    {
        $store   = $this->merchant->getStoreForUser($request->user());
        $updated = $this->merchant->confirmLogoUpload($store, $request->input('key'));

        return ApiResponse::success('Logo updated successfully.', new StoreResource($updated));
    }

    public function deleteLogo(Request $request): \Illuminate\Http\Response
    {
        $this->merchant->deleteLogo($this->merchant->getStoreForUser($request->user()));

        return response()->noContent();
    }

    // ── Banner ────────────────────────────────────────────────────────────────

    public function uploadBanner(UploadStoreMediaRequest $request): JsonResponse
    {
        $store  = $this->merchant->getStoreForUser($request->user());
        $result = $this->merchant->generateBannerPresignedUrl(
            $store,
            $request->input('filename'),
            $request->input('mime'),
        );

        return ApiResponse::success('Banner presigned URL generated.', $result, 201);
    }

    public function confirmBanner(ConfirmStoreMediaRequest $request): JsonResponse
    {
        $store   = $this->merchant->getStoreForUser($request->user());
        $updated = $this->merchant->confirmBannerUpload($store, $request->input('key'));

        return ApiResponse::success('Banner updated successfully.', new StoreResource($updated));
    }

    public function deleteBanner(Request $request): \Illuminate\Http\Response
    {
        $this->merchant->deleteBanner($this->merchant->getStoreForUser($request->user()));

        return response()->noContent();
    }
}
