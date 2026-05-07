<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreVariantRequest;
use App\Http\Requests\Product\UpdateVariantRequest;
use App\Http\Resources\Product\VariantResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function store(StoreVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageVariants', $product);
        $variant = $this->products->addVariant($product, $request->validated());

        return ApiResponse::success('Variant created.', new VariantResource($variant), 201);
    }

    public function update(UpdateVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('manageVariants', $product);
        $updated = $this->products->updateVariant($variant, $request->validated());

        return ApiResponse::success('Variant updated.', new VariantResource($updated));
    }

    public function destroy(Product $product, ProductVariant $variant): \Illuminate\Http\Response
    {
        $this->authorize('manageVariants', $product);
        $this->products->deleteVariant($variant);

        return response()->noContent();
    }
}
