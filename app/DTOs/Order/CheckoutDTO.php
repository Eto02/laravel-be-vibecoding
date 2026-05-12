<?php

namespace App\DTOs\Order;

use App\Http\Requests\Order\CheckoutRequest;

readonly class CheckoutDTO
{
    public function __construct(
        /** @var CheckoutItemDTO[] */
        public array   $items,
        public ?string $voucherCode,
    ) {}

    public static function fromRequest(CheckoutRequest $request): self
    {
        $items = array_map(
            fn (array $item) => new CheckoutItemDTO(
                storeId:         (int) $item['store_id'],
                addressId:       (int) $item['address_id'],
                shippingCourier: $item['shipping_courier'],
                shippingService: $item['shipping_service'],
                shippingFee:     (int) $item['shipping_fee'],
                notes:           $item['notes'] ?? null,
                itemIds:         isset($item['item_ids']) ? array_map('intval', $item['item_ids']) : null,
            ),
            $request->input('items', [])
        );

        return new self(
            items:       $items,
            voucherCode: $request->input('voucher_code'),
        );
    }
}
