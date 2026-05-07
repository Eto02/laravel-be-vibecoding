<?php

namespace App\Http\Controllers\Api\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddWishlistItemRequest;
use App\Http\Resources\Cart\WishlistResource;
use App\Http\Responses\ApiResponse;
use App\Services\Cart\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlist,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wishlist = $this->wishlist->getForUser($request->user());

        return ApiResponse::success('Wishlist retrieved.', new WishlistResource($wishlist));
    }

    public function store(AddWishlistItemRequest $request): JsonResponse
    {
        $wishlist = $this->wishlist->add($request->user(), $request->integer('product_id'));

        return ApiResponse::success('Product added to wishlist.', new WishlistResource($wishlist), 201);
    }

    public function destroy(Request $request, int $productId): \Illuminate\Http\Response
    {
        $this->wishlist->remove($request->user(), $productId);

        return response()->noContent();
    }

    public function check(Request $request, int $productId): JsonResponse
    {
        $wishlisted = $this->wishlist->check($request->user(), $productId);

        return ApiResponse::success('Wishlist check.', ['is_wishlisted' => $wishlisted]);
    }
}
