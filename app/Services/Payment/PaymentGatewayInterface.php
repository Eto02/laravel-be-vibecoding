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

    public function getPaymentStatus(string $gatewayRef): array;

    public function refundPayment(string $chargeRef, int $amount): array;

    public function verifyWebhook(Request $request): bool;

    /**
     * Cancel / void a pending charge before it is paid.
     * $method is needed because Xendit has different endpoints per payment method.
     * Should be best-effort: if gateway already expired the charge, return true silently.
     */
    public function cancelCharge(string $chargeRef, string $method): bool;

    /**
     * Normalize gateway webhook payload to a standard format.
     * Returns: ['event' => string, 'external_id' => string, 'status' => 'paid'|'failed'|'expired'|'pending', 'amount' => int]
     */
    public function parseWebhookPayload(Request $request): array;

    /**
     * Normalize a raw gateway API response (from getPaymentStatus()) into
     * the same internal format as parseWebhookPayload().
     * Returns: ['status' => 'paid'|'expired'|'failed'|'pending', 'amount' => int]
     */
    public function parseStatusResponse(array $apiResponse): array;
}
