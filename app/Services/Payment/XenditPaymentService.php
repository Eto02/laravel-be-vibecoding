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
        return $this->createInvoice($data);
    }

    private function createInvoice(array $data): array
    {
        $invoiceDuration = isset($data['expires_at'])
            ? max(60, (int) now()->diffInSeconds(now()->parse($data['expires_at'])))
            : config('payment.expiry_minutes', 15) * 60;

        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/v2/invoices", [
                'external_id'      => $data['external_id'],
                'amount'           => $this->toIDR($data['amount']),
                'description'      => $data['description'] ?? 'Payment',
                'invoice_duration' => $invoiceDuration,
                'currency'         => 'IDR',
                'customer'         => [
                    'given_names' => $data['customer_name'] ?? 'Customer',
                    'email'       => $data['customer_email'] ?? null,
                ],
            ]);

        $this->assertSuccess($response, 'Failed to create Xendit invoice');
        $body = $response->json();

        return [
            'gateway_ref'     => $body['id'],
            'redirect_url'    => $body['invoice_url'],
            'payment_details' => [
                'external_id' => $data['external_id'],
                'invoice_id'  => $body['id'],
                'invoice_url' => $body['invoice_url'],
            ],
            'expires_at' => $body['expiry_date'] ?? null,
        ];
    }

    public function cancelCharge(string $chargeRef, string $method): bool
    {
        try {
            return $this->expireInvoice($chargeRef);
        } catch (\Throwable) {
            return false;
        }
    }

    private function expireInvoice(string $id): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/v2/invoices/{$id}/expire!");

        return $response->successful() || in_array($response->status(), [404, 422]);
    }

    private function toIDR(int $cents): int
    {
        return (int) round($cents / 100);
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
            return false;
        }

        return hash_equals($this->webhookToken, (string) $request->header('X-CALLBACK-TOKEN', ''));
    }

    public function parseWebhookPayload(Request $request): array
    {
        $payload       = $request->json()->all();
        $invoiceStatus = strtoupper($payload['status'] ?? '');

        $status = match ($invoiceStatus) {
            'PAID'    => 'paid',
            'EXPIRED' => 'expired',
            default   => 'failed',
        };

        return [
            'event'       => $status === 'paid' ? 'payment.succeeded' : 'payment.' . $status,
            'external_id' => $payload['external_id'] ?? '',
            'status'      => $status,
            'amount'      => (int) ($payload['paid_amount'] ?? $payload['amount'] ?? 0) * 100,
        ];
    }

    private function assertSuccess(\Illuminate\Http\Client\Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException("{$message}: " . $response->body());
        }
    }
}
