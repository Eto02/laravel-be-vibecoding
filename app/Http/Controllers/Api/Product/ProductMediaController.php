<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ConfirmProductMediaRequest;
use App\Http\Requests\Product\ReorderProductMediaRequest;
use App\Http\Requests\Product\StoreProductMediaRequest;
use App\Http\Resources\Product\ProductMediaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;

class ProductMediaController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function generateUrl(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageMedia', $product);
        $result = $this->products->generateMediaPresignedUrl($product, $request->filename, $request->mime);

        return ApiResponse::success('Presigned URL generated.', $result);
    }

    public function confirm(ConfirmProductMediaRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageMedia', $product);
        $media = $this->products->confirmMediaUpload($product, $request->key, $request->type ?? 'image');

        return ApiResponse::success('Media saved.', new ProductMediaResource($media), 201);
    }

    public function destroy(Product $product, ProductMedia $media): \Illuminate\Http\Response
    {
        $this->authorize('manageMedia', $product);
        $this->products->deleteMedia($product, $media);

        return response()->noContent();
    }

    public function reorder(ReorderProductMediaRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageMedia', $product);
        $this->products->reorderMedia($product, $request->items);

        return ApiResponse::success('Media reordered.');
    }
}
