<?php

namespace App\Http\Controllers\Api\Product;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductStatusRequest;
use App\Http\Resources\Product\ProductListResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\Merchant\MerchantService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
        private readonly MerchantService $merchant,
    ) {}

    // ── Public ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->getPublicListing($request->only([
            'search', 'category', 'min_price', 'max_price', 'sort', 'store',
        ]));

        return ApiResponse::success('Products retrieved.', ProductListResource::collection($paginator), paginationMeta: [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $product = $this->products->getProductDetail($slug, $request->user());

        return ApiResponse::success('Product retrieved.', new ProductResource($product));
    }

    public function variants(Product $product): JsonResponse
    {
        $product->load('variants');

        return ApiResponse::success('Variants retrieved.', \App\Http\Resources\Product\VariantResource::collection($product->variants));
    }

    // ── Merchant ──────────────────────────────────────────────────────────────

    public function merchantIndex(Request $request): JsonResponse
    {
        $storeId = $this->merchant->getStoreForUser($request->user())->id;
        $paginator = $this->products->getMerchantProducts($storeId);

        return ApiResponse::success('Products retrieved.', ProductListResource::collection($paginator), paginationMeta: [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $store = $this->merchant->getStoreForUser($request->user());
        $product = $this->products->create(CreateProductDTO::fromRequest($request, $store->id));

        return ApiResponse::success('Product created.', new ProductResource($product), 201);
    }

    public function merchantShow(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $product->load(['category', 'variants', 'media']);

        return ApiResponse::success('Product retrieved.', new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $updated = $this->products->update($product, UpdateProductDTO::fromRequest($request));

        return ApiResponse::success('Product updated.', new ProductResource($updated));
    }

    public function destroy(Request $request, Product $product): \Illuminate\Http\Response
    {
        $this->authorize('delete', $product);
        $this->products->delete($product);

        return response()->noContent();
    }

    public function updateStatus(UpdateProductStatusRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $updated = $this->products->updateStatus($product, ProductStatus::from($request->status));

        return ApiResponse::success('Status updated.', new ProductResource($updated));
    }
}
