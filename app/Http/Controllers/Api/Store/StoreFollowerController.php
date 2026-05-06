<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\StoreSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Store;
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
        $store     = Store::where('slug', $slug)->firstOrFail();
        $followers = $store->followers()->with('user:id,name,avatar')->paginate(20);

        return ApiResponse::success('Followers retrieved.', $followers->items(), paginationMeta: [
            'current_page' => $followers->currentPage(),
            'last_page'    => $followers->lastPage(),
            'per_page'     => $followers->perPage(),
            'total'        => $followers->total(),
        ]);
    }

    public function follow(Request $request, string $slug): JsonResponse
    {
        $store = Store::where('slug', $slug)->firstOrFail();
        $this->merchant->follow($request->user(), $store);

        return ApiResponse::success('Store followed successfully.');
    }

    public function unfollow(Request $request, string $slug): JsonResponse
    {
        $store = Store::where('slug', $slug)->firstOrFail();
        $this->merchant->unfollow($request->user(), $store);

        return ApiResponse::success('Store unfollowed successfully.');
    }
}
