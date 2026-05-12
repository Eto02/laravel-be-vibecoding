<?php

namespace App\DTOs\Order;

readonly class CheckoutItemDTO
{
    public function __construct(
        public int     $storeId,
        public int     $addressId,
        public string  $shippingCourier,
        public string  $shippingService,
        public int     $shippingFee,
        public ?string $notes,
        /** @var int[]|null Specific cart_item IDs to checkout. Null = all items from this store. */
        public ?array  $itemIds = null,
    ) {}
}
