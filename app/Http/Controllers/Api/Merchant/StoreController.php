<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpdateStoreRequest;
use App\Http\Resources\Merchant\StoreResource;
use App\Http\Responses\ApiResponse;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchant,
    ) {}

    public function update(UpdateStoreRequest $request): JsonResponse
    {
        $store   = $request->user()->store;
        $updated = $this->merchant->update($store, $request->validated());

        return ApiResponse::success('Store updated successfully.', new StoreResource($updated));
    }
}
