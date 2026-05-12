<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\StoreFollowerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Merchant\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreFollowerController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchant,
    ) {}

    public function followers(string $slug): JsonResponse
    {
        $store     = $this->merchant->getPublicProfile($slug);
        $followers = $this->merchant->getFollowers($store);

        return ApiResponse::success('Followers retrieved.', StoreFollowerResource::collection($followers->items()), paginationMeta: [
            'current_page' => $followers->currentPage(),
            'last_page'    => $followers->lastPage(),
            'per_page'     => $followers->perPage(),
            'total'        => $followers->total(),
        ]);
    }

    public function follow(Request $request, string $slug): JsonResponse
    {
        $store = $this->merchant->getPublicProfile($slug);
        $this->merchant->follow($request->user(), $store);

        return ApiResponse::success('Store followed successfully.', null, 201);
    }

    public function unfollow(Request $request, string $slug): \Illuminate\Http\Response
    {
        $store = $this->merchant->getPublicProfile($slug);
        $this->merchant->unfollow($request->user(), $store);

        return response()->noContent();
    }
}
