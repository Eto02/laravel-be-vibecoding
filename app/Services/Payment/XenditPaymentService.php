<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class XenditPaymentService implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $webhookToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey    = config('xendit.secret_key');
        $this->webhookToken = config('xendit.webhook_token');
        $this->baseUrl      = rtrim(config('xendit.base_url', 'https://api.xendit.co'), '/');
    }

    public function createCharge(array $data): array
    {
        return match ($data['method'] ?? 'invoice') {
            'virtual_account' => $this->createVirtualAccount($data),
            'qris'            => $this->createQrisCharge($data),
            'ewallet'         => $this->createEWalletCharge($data),
            default           => $this->createInvoice($data),
        };
    }

    private function toIDR(int $cents): int
    {
        return (int) round($cents / 100);
    }

    private function createInvoice(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/v2/invoices", [
                'external_id'      => $data['external_id'],
                'amount'           => $this->toIDR($data['amount']),
                'description'      => $data['description'] ?? 'Payment',
                'invoice_duration' => 86400,
                'currency'         => 'IDR',
            ]);

        $this->assertSuccess($response, 'Failed to create Xendit invoice');
        $body = $response->json();

        return [
            'gateway_ref'     => $body['id'],
            'redirect_url'    => $body['invoice_url'],
            'payment_details' => ['invoice_id' => $body['id'], 'invoice_url' => $body['invoice_url']],
            'expires_at'      => $body['expiry_date'] ?? null,
        ];
    }

    private function createVirtualAccount(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/callback_virtual_accounts", [
                'external_id'       => $data['external_id'],
                'bank_code'         => strtoupper($data['bank_code'] ?? 'BCA'),
                'name'              => $data['customer_name'] ?? 'Customer',
                'expected_amount'   => $this->toIDR($data['amount']),
                'is_closed'         => true,
                'is_single_use'     => true,
                'expiration_date'   => $data['expires_at'] ?? now()->addHours(24)->toISOString(),
            ]);

        $this->assertSuccess($response, 'Failed to create Xendit virtual account');
        $body = $response->json();

        return [
            'gateway_ref'     => $body['id'],
            'redirect_url'    => null,
            'payment_details' => [
                'external_id'        => $data['external_id'], // needed for sandbox simulate API
                'bank_code'          => $body['bank_code'],
                'account_number'     => $body['account_number'],
                'virtual_account_id' => $body['id'],
            ],
            'expires_at' => $body['expiration_date'] ?? null,
        ];
    }

    private function createQrisCharge(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/qr_codes", [
                'external_id'     => $data['external_id'],
                'type'            => 'DYNAMIC',
                'currency'        => 'IDR',
                'amount'          => $this->toIDR($data['amount']),
                'expires_at'      => now()->addSeconds($data['qris_ttl_seconds'] ?? 300)->toISOString(),
                'callback_url'    => $data['callback_url'] ?? config('xendit.webhook_url'),
            ]);

        $this->assertSuccess($response, 'Failed to create Xendit QRIS charge');
        $body = $response->json();

        return [
            'gateway_ref'     => $body['id'],
            'redirect_url'    => null,
            'payment_details' => [
                'external_id' => $data['external_id'], // needed for sandbox simulate API
                'qr_id'       => $body['id'],
                'qr_string'   => $body['qr_string'] ?? null,
            ],
            'expires_at' => $body['expires_at'] ?? null,
        ];
    }

    private function createEWalletCharge(array $data): array
    {
        $ewalletType = strtoupper($data['ewallet_type'] ?? 'GOPAY');

        $payload = [
            'reference_id'        => $data['external_id'],
            'currency'            => 'IDR',
            'amount'              => $this->toIDR($data['amount']),
            'checkout_method'     => 'ONE_TIME_PAYMENT',
            'channel_code'        => $ewalletType,
            'channel_properties'  => $this->buildEWalletChannelProperties($ewalletType, $data),
        ];

        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/ewallets/charges", $payload);

        $this->assertSuccess($response, 'Failed to create Xendit e-wallet charge');
        $body = $response->json();

        $checkoutUrl = $body['actions']['desktop_web_checkout_url']
            ?? $body['actions']['mobile_web_checkout_url']
            ?? $body['actions']['mobile_deeplink_checkout_url']
            ?? null;

        return [
            'gateway_ref'     => $body['id'],
            'redirect_url'    => $checkoutUrl,
            'payment_details' => [
                'external_id'  => $data['external_id'], // needed for sandbox simulate API
                'ewallet_type' => $ewalletType,
                'charge_id'    => $body['id'],
                'checkout_url' => $checkoutUrl,
            ],
            'expires_at' => null,
        ];
    }

    private function buildEWalletChannelProperties(string $type, array $data): array
    {
        return match ($type) {
            'OVO'      => ['mobile_number' => $data['phone'] ?? ''],
            'SHOPEEPAY' => ['success_redirect_url' => $data['success_redirect_url'] ?? config('app.url')],
            default    => [
                'success_redirect_url' => $data['success_redirect_url'] ?? config('app.url'),
                'failure_redirect_url' => $data['failure_redirect_url'] ?? config('app.url'),
            ],
        };
    }

    public function cancelCharge(string $chargeRef, string $method): bool
    {
        try {
            return match ($method) {
                'virtual_account' => $this->cancelVirtualAccount($chargeRef),
                'ewallet'         => $this->cancelEWalletCharge($chargeRef),
                'qris'            => $this->deactivateQris($chargeRef),
                default           => $this->expireInvoice($chargeRef),
            };
        } catch (\Throwable) {
            return false; // best-effort — gateway already expired or ref invalid
        }
    }

    private function cancelVirtualAccount(string $id): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->patch("{$this->baseUrl}/callback_virtual_accounts/{$id}", [
                'expiration_date' => now()->subSecond()->toISOString(),
            ]);

        return $response->successful() || in_array($response->status(), [404, 422]);
    }

    private function cancelEWalletCharge(string $id): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/ewallets/charges/{$id}/cancel");

        return $response->successful() || in_array($response->status(), [404, 409]);
    }

    private function deactivateQris(string $id): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->patch("{$this->baseUrl}/qr_codes/{$id}", ['status' => 'INACTIVE']);

        return $response->successful() || in_array($response->status(), [404, 422]);
    }

    private function expireInvoice(string $id): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/v2/invoices/{$id}/expire!");

        return $response->successful() || in_array($response->status(), [404, 422]);
    }

    public function getPaymentStatus(string $externalId): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/v2/invoices/{$externalId}");

        $this->assertSuccess($response, 'Failed to fetch Xendit payment status');

        return $response->json();
    }

    public function refundPayment(string $chargeRef, int $amount): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/refunds", [
                'payment_request_id' => $chargeRef,
                'amount'             => $this->toIDR($amount),
                'reason'             => 'OTHERS',
            ]);

        $this->assertSuccess($response, 'Failed to refund Xendit payment');

        return $response->json();
    }

    public function verifyWebhook(Request $request): bool
    {
        if (empty($this->webhookToken)) {
            return false; // Reject all webhooks when token is not configured
        }

        $callbackToken = $request->header('X-CALLBACK-TOKEN', '');

        return hash_equals($this->webhookToken, $callbackToken);
    }

    public function parseWebhookPayload(Request $request): array
    {
        $payload = $request->json()->all();
        $event   = $payload['event'] ?? '';

        // Virtual account — payment received (has both payment_id and account_number)
        if (isset($payload['payment_id']) && isset($payload['account_number'])) {
            return [
                'event'       => 'payment.succeeded',
                'external_id' => $payload['external_id'] ?? '',
                'status'      => 'paid',
                'amount'      => (int) ($payload['amount'] ?? 0),
            ];
        }

        // Virtual account — creation/update notification (has account_number but no payment_id)
        // Xendit fires this when a VA is first registered. It is NOT a payment event.
        if (isset($payload['account_number']) && ! isset($payload['payment_id'])) {
            return [
                'event'       => 'va.registered',
                'external_id' => $payload['external_id'] ?? '',
                'status'      => 'pending',
                'amount'      => 0,
            ];
        }

        // QRIS payment
        if (str_contains($event, 'qr_code')) {
            return [
                'event'       => 'payment.succeeded',
                'external_id' => $payload['external_id'] ?? $payload['reference_id'] ?? '',
                'status'      => 'paid',
                'amount'      => (int) ($payload['amount'] ?? 0),
            ];
        }

        // E-wallet
        if (str_contains($event, 'ewallet')) {
            $chargeStatus = strtoupper($payload['data']['status'] ?? $payload['charge_status'] ?? '');
            $status       = match ($chargeStatus) {
                'SUCCEEDED' => 'paid',
                'FAILED'    => 'failed',
                'VOIDED'    => 'expired',
                default     => 'failed',
            };

            return [
                'event'       => $status === 'paid' ? 'payment.succeeded' : 'payment.failed',
                'external_id' => $payload['data']['reference_id'] ?? $payload['external_id'] ?? '',
                'status'      => $status,
                'amount'      => (int) ($payload['data']['charge_amount'] ?? $payload['amount'] ?? 0),
            ];
        }

        // Hosted invoice (legacy + fallback)
        $invoiceStatus = strtoupper($payload['status'] ?? '');
        $status        = match ($invoiceStatus) {
            'PAID'    => 'paid',
            'EXPIRED' => 'expired',
            default   => 'failed',
        };

        return [
            'event'       => $status === 'paid' ? 'payment.succeeded' : 'payment.' . $status,
            'external_id' => $payload['external_id'] ?? '',
            'status'      => $status,
            'amount'      => (int) ($payload['paid_amount'] ?? $payload['amount'] ?? 0),
        ];
    }

    private function assertSuccess(\Illuminate\Http\Client\Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException("{$message}: " . $response->body());
        }
    }
}
