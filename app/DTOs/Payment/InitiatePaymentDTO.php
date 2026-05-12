<?php

namespace App\DTOs\Payment;

use Illuminate\Foundation\Http\FormRequest;

readonly class InitiatePaymentDTO
{
    public function __construct(
        public int     $orderId,
        public string  $gateway,
        public ?string $idempotencyKey,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId:        $request->integer('order_id'),
            gateway:        $request->string('gateway')->toString(),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        );
    }

    public static function fromSwitchRequest(FormRequest $request, int $orderId): self
    {
        return new self(
            orderId:        $orderId,
            gateway:        $request->string('gateway')->toString(),
            idempotencyKey: null,
        );
    }
}
