<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MidtransPaymentService implements PaymentGatewayInterface
{
    private string $serverKey;
    private string $snapUrl;

    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key');
        $this->snapUrl   = config('midtrans.snap_url', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
    }

    public function createCharge(array $data): array
    {
        $grossAmount = (int) $data['amount'];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->post($this->snapUrl, [
                'transaction_details' => [
                    'order_id'    => $data['external_id'],
                    'gross_amount' => $grossAmount,
                ],
                'customer_details' => [
                    'first_name' => $data['customer_name'] ?? 'Customer',
                    'email'      => $data['customer_email'] ?? null,
                    'phone'      => $data['phone'] ?? null,
                ],
                'callbacks' => [
                    'finish' => $data['success_redirect_url'] ?? config('app.url'),
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to create Midtrans Snap transaction: ' . $response->body());
        }

        $body = $response->json();

        return [
            'gateway_ref'     => $data['external_id'],
            'redirect_url'    => $body['redirect_url'],
            'payment_details' => [
                'external_id'  => $data['external_id'],
                'snap_token'   => $body['token'],
                'redirect_url' => $body['redirect_url'],
            ],
            'expires_at' => null,
        ];
    }

    public function cancelCharge(string $chargeRef, string $method): bool
    {
        $baseUrl = config('midtrans.is_production', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->post("{$baseUrl}/v2/{$chargeRef}/cancel");

            // 412 = transaction cannot be cancelled (already expired/settled) — treat as success
            return $response->successful() || in_array($response->status(), [404, 412]);
        } catch (\Throwable) {
            return false; // best-effort
        }
    }

    public function getPaymentStatus(string $externalId): array
    {
        $apiUrl = str_replace('/snap/v1/transactions', '', $this->snapUrl);
        $baseUrl = config('midtrans.is_production', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $response = Http::withBasicAuth($this->serverKey, '')
            ->get("{$baseUrl}/v2/{$externalId}/status");

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to get Midtrans payment status: ' . $response->body());
        }

        return $response->json();
    }

    public function refundPayment(string $chargeRef, int $amount): array
    {
        $baseUrl = config('midtrans.is_production', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $response = Http::withBasicAuth($this->serverKey, '')
            ->post("{$baseUrl}/v2/{$chargeRef}/refund", [
                'amount' => $amount,
                'reason' => 'Customer refund request',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to refund Midtrans payment: ' . $response->body());
        }

        return $response->json();
    }

    public function verifyWebhook(Request $request): bool
    {
        $payload      = $request->json()->all();
        $orderId      = $payload['order_id'] ?? '';
        $statusCode   = $payload['status_code'] ?? '';
        $grossAmount  = $payload['gross_amount'] ?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($expected, (string) $signatureKey);
    }

    public function parseWebhookPayload(Request $request): array
    {
        $payload           = $request->json()->all();
        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus       = $payload['fraud_status'] ?? '';

        $isPaid = ($transactionStatus === 'capture' && $fraudStatus === 'accept')
            || $transactionStatus === 'settlement';

        $status = match (true) {
            $isPaid                                             => 'paid',
            in_array($transactionStatus, ['cancel', 'expire']) => 'expired',
            $transactionStatus === 'pending'                    => 'pending', // risk review — no state change
            default                                             => 'failed',
        };

        return [
            'event'       => $status === 'paid' ? 'payment.succeeded' : 'payment.' . $status,
            'external_id' => $payload['order_id'] ?? '',
            'status'      => $status,
            'amount'      => (int) str_replace([',', '.00'], '', $payload['gross_amount'] ?? '0'),
        ];
    }
}
