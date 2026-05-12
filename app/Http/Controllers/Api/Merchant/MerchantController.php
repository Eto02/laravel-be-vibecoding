<?php

namespace App\Http\Controllers\Api\Merchant;

use App\DTOs\Merchant\RegisterMerchantDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\ConfirmKycRequest;
use App\Http\Requests\Merchant\RegisterMerchantRequest;
use App\Http\Requests\Merchant\UploadKycRequest;
use App\Http\Resources\Merchant\StoreDocumentResource;
use App\Http\Resources\Merchant\StoreResource;
use App\Http\Responses\ApiResponse;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchant,
    ) {}

    public function register(RegisterMerchantRequest $request): JsonResponse
    {
        $store = $this->merchant->register(
            $request->user(),
            RegisterMerchantDTO::fromRequest($request),
        );

        return ApiResponse::success('Store registered successfully.', new StoreResource($store), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $store = $this->merchant->getStoreForUser($request->user());

        return ApiResponse::success('Store retrieved.', new StoreResource($store));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $store = $this->merchant->getStoreForUser($request->user());
        $data  = $this->merchant->getDashboard($store);

        return ApiResponse::success('Dashboard retrieved.', [
            'store'          => new StoreResource($data['store']),
            'follower_count' => $data['follower_count'],
            'rating_avg'     => $data['rating_avg'],
            'total_sales'    => $data['total_sales'],
        ]);
    }

    public function uploadKyc(UploadKycRequest $request): JsonResponse
    {
        $store  = $this->merchant->getStoreForUser($request->user());
        $result = $this->merchant->generateKycPresignedUrl(
            $store,
            $request->input('type'),
            $request->input('filename'),
            $request->input('mime'),
        );

        return ApiResponse::success('KYC presigned URL generated.', $result, 201);
    }

    public function confirmKyc(ConfirmKycRequest $request): JsonResponse
    {
        $store    = $this->merchant->getStoreForUser($request->user());
        $document = $this->merchant->confirmKycUpload(
            $store,
            $request->input('type'),
            $request->input('key'),
        );

        return ApiResponse::success('KYC document submitted successfully.', new StoreDocumentResource($document));
    }

    public function reuploadKyc(UploadKycRequest $request): JsonResponse
    {
        $store  = $this->merchant->getStoreForUser($request->user());
        $result = $this->merchant->generateKycReuploadUrl(
            $store,
            $request->input('type'),
            $request->input('filename'),
            $request->input('mime'),
        );

        return ApiResponse::success('KYC re-upload URL generated.', $result, 201);
    }

    public function confirmKycReupload(ConfirmKycRequest $request): JsonResponse
    {
        $store    = $this->merchant->getStoreForUser($request->user());
        $document = $this->merchant->confirmKycUpload(
            $store,
            $request->input('type'),
            $request->input('key'),
        );

        return ApiResponse::success('KYC document re-submitted successfully.', new StoreDocumentResource($document));
    }
}
