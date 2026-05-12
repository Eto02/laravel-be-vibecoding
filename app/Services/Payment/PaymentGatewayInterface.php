<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create a charge for the given method.
     * $data keys: external_id, amount, method, bank_code, ewallet_type, phone, success_redirect_url, expires_at
     * Returns: gateway_ref, redirect_url|null, payment_details (array), expires_at (string|null)
     */
    public function createCharge(array $data): array;

    public function getPaymentStatus(string $externalId): array;

    public function refundPayment(string $chargeRef, int $amount): array;

    public function verifyWebhook(Request $request): bool;

    /**
     * Normalize gateway webhook payload to a standard format.
     * Returns: ['event' => string, 'external_id' => string, 'status' => 'paid'|'failed'|'expired', 'amount' => int]
     */
    public function parseWebhookPayload(Request $request): array;
}
