<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function createPaymentIntent(array $data): array;

    public function capturePayment(string $paymentId): array;

    public function refundPayment(string $paymentId, int $amount): array;

    public function verifyWebhook(Request $request): bool;
}
