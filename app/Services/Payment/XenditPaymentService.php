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
        $this->baseUrl      = rtrim(config('xendit.base_url'), '/');
    }

    public function createPaymentIntent(array $data): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/v2/invoices", [
                'external_id'      => $data['external_id'],
                'amount'           => $data['amount'],
                'description'      => $data['description'] ?? 'Payment',
                'invoice_duration' => $data['invoice_duration'] ?? 86400,
                'currency'         => $data['currency'] ?? 'IDR',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to create Xendit invoice: ' . $response->body());
        }

        return $response->json();
    }

    public function capturePayment(string $paymentId): array
    {
        // Xendit invoices are captured automatically; this fetches current status.
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/v2/invoices/{$paymentId}");

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch Xendit invoice: ' . $response->body());
        }

        return $response->json();
    }

    public function refundPayment(string $paymentId, int $amount): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/refunds", [
                'payment_request_id' => $paymentId,
                'amount'             => $amount,
                'reason'             => 'OTHERS',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to refund Xendit payment: ' . $response->body());
        }

        return $response->json();
    }

    public function verifyWebhook(Request $request): bool
    {
        $callbackToken = $request->header('X-CALLBACK-TOKEN', '');

        return hash_equals($this->webhookToken, (string) $callbackToken);
    }
}
