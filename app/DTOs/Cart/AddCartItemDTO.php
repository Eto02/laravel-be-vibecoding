<?php

namespace App\DTOs\Cart;

use App\Http\Requests\Cart\AddCartItemRequest;

readonly class AddCartItemDTO
{
    public function __construct(
        public int $variantId,
        public int $quantity,
    ) {}

    public static function fromRequest(AddCartItemRequest $request): self
    {
        return new self(
            variantId: $request->integer('variant_id'),
            quantity:  $request->integer('quantity'),
        );
    }
}
