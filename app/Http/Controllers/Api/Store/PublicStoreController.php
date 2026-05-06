<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\StoreSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\JsonResponse;

class PublicStoreController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchant,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $store = $this->merchant->getPublicProfile($slug);

        return ApiResponse::success('Store retrieved.', new StoreSummaryResource($store));
    }

    public function products(string $slug): JsonResponse
    {
        return ApiResponse::error('Store products endpoint is not yet available.', 501);
    }
}
