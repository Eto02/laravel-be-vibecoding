<?php

namespace App\DTOs\Payment;

use Illuminate\Foundation\Http\FormRequest;

readonly class InitiatePaymentDTO
{
    public function __construct(
        public int $orderId,
        public string $gateway,
        public string $method,
        public ?string $bankCode,
        public ?string $ewalletType,
        public ?string $phone,
        public ?string $successRedirectUrl,
        public ?string $idempotencyKey,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId:            $request->integer('order_id'),
            gateway:            $request->string('gateway')->toString(),
            method:             $request->string('method')->toString(),
            bankCode:           $request->input('bank_code'),
            ewalletType:        $request->input('ewallet_type'),
            phone:              $request->input('phone'),
            successRedirectUrl: $request->input('success_redirect_url'),
            idempotencyKey:     $request->header('X-Idempotency-Key'),
        );
    }

    public static function fromSwitchRequest(FormRequest $request, int $orderId): self
    {
        return new self(
            orderId:            $orderId,
            gateway:            $request->string('gateway')->toString(),
            method:             $request->string('method')->toString(),
            bankCode:           $request->input('bank_code'),
            ewalletType:        $request->input('ewallet_type'),
            phone:              $request->input('phone'),
            successRedirectUrl: $request->input('success_redirect_url'),
            idempotencyKey:     null, // switch always creates fresh — no idempotency
        );
    }
}
