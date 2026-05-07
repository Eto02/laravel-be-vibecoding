<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\StoreSummaryResource;
use App\Http\Resources\Product\ProductListResource;
use App\Http\Responses\ApiResponse;
use App\Services\Merchant\MerchantService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicStoreController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchant,
        private readonly ProductService $products,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $store = $this->merchant->getPublicProfile($slug);

        return ApiResponse::success('Store retrieved.', new StoreSummaryResource($store));
    }

    public function products(Request $request, string $slug): JsonResponse
    {
        $store = $this->merchant->getPublicProfile($slug);
        $paginator = $this->products->getStoreProducts($store, $request->only(['page']));

        return ApiResponse::success('Store products retrieved.', ProductListResource::collection($paginator), paginationMeta: [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }
}
