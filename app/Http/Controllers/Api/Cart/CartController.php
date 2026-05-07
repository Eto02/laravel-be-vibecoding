<?php

namespace App\Http\Controllers\Api\Cart;

use App\DTOs\Cart\AddCartItemDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\Cart\CartResource;
use App\Http\Responses\ApiResponse;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cart = $this->cart->getForUser($request->user());

        return ApiResponse::success('Cart retrieved.', new CartResource($cart));
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->cart->add($request->user(), AddCartItemDTO::fromRequest($request));

        return ApiResponse::success('Item added to cart.', new CartResource($cart), 201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->cart->update($request->user(), $cartItem, $request->integer('quantity'));

        return ApiResponse::success('Cart item updated.', new CartResource($cart));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->cart->remove($request->user(), $cartItem);

        return ApiResponse::success('Item removed from cart.', new CartResource($cart));
    }

    public function clear(Request $request): \Illuminate\Http\Response
    {
        $this->cart->clear($request->user());

        return response()->noContent();
    }
}
