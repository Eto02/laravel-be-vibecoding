<?php

namespace Tests\Unit\Services\Payment;

use App\Services\Payment\MidtransPaymentService;
use Tests\TestCase;

class MidtransParseStatusResponseTest extends TestCase
{
    private MidtransPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['midtrans.server_key'    => 'test-key']);
        config(['midtrans.snap_url'      => 'https://app.sandbox.midtrans.com/snap/v1/transactions']);
        config(['midtrans.is_production' => false]);
        $this->service = new MidtransPaymentService();
    }

    public function test_capture_with_accept_fraud_status_normalizes_to_paid(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'capture',
            'fraud_status'       => 'accept',
            'gross_amount'       => '100000.00',
        ]);

        $this->assertSame('paid', $result['status']);
        $this->assertSame(10000000, $result['amount']);
    }

    public function test_settlement_normalizes_to_paid(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'settlement',
            'fraud_status'       => '',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('paid', $result['status']);
    }

    public function test_capture_with_challenge_normalizes_to_pending(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'capture',
            'fraud_status'       => 'challenge',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('pending', $result['status']);
    }

    public function test_cancel_normalizes_to_expired(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'cancel',
            'fraud_status'       => '',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('expired', $result['status']);
    }

    public function test_expire_normalizes_to_expired(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'expire',
            'fraud_status'       => '',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('expired', $result['status']);
    }

    public function test_deny_normalizes_to_expired(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'deny',
            'fraud_status'       => '',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('expired', $result['status']);
    }

    public function test_failure_normalizes_to_failed(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'failure',
            'fraud_status'       => '',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('failed', $result['status']);
    }

    public function test_pending_normalizes_to_pending(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'pending',
            'fraud_status'       => '',
            'gross_amount'       => '50000.00',
        ]);

        $this->assertSame('pending', $result['status']);
    }

    public function test_amount_is_converted_from_rupiah_float_to_cents(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'settlement',
            'fraud_status'       => '',
            'gross_amount'       => '75000.00',
        ]);

        $this->assertSame(7500000, $result['amount']);
    }

    public function test_large_amount_with_comma_separator_converts_correctly(): void
    {
        $result = $this->service->parseStatusResponse([
            'transaction_status' => 'settlement',
            'fraud_status'       => '',
            'gross_amount'       => '1500000.00',
        ]);

        $this->assertSame(150000000, $result['amount']);
    }

    // --- parseWebhookPayload ---

    private function webhookRequest(array $payload): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/webhook', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        return $request;
    }

    public function test_webhook_capture_accept_normalizes_to_paid_with_correct_amount(): void
    {
        $result = $this->service->parseWebhookPayload($this->webhookRequest([
            'transaction_status' => 'capture',
            'fraud_status'       => 'accept',
            'order_id'           => 'PAY-ABC123-1',
            'gross_amount'       => '100000.00',
        ]));

        $this->assertSame('paid', $result['status']);
        $this->assertSame('payment.succeeded', $result['event']);
        $this->assertSame(10000000, $result['amount']);
    }

    public function test_webhook_capture_challenge_normalizes_to_pending(): void
    {
        $result = $this->service->parseWebhookPayload($this->webhookRequest([
            'transaction_status' => 'capture',
            'fraud_status'       => 'challenge',
            'order_id'           => 'PAY-ABC123-1',
            'gross_amount'       => '100000.00',
        ]));

        $this->assertSame('pending', $result['status']);
    }

    public function test_webhook_deny_normalizes_to_expired(): void
    {
        $result = $this->service->parseWebhookPayload($this->webhookRequest([
            'transaction_status' => 'deny',
            'fraud_status'       => '',
            'order_id'           => 'PAY-ABC123-1',
            'gross_amount'       => '100000.00',
        ]));

        $this->assertSame('expired', $result['status']);
    }

    public function test_webhook_amount_uses_multiplication_not_string_replace(): void
    {
        $result = $this->service->parseWebhookPayload($this->webhookRequest([
            'transaction_status' => 'settlement',
            'fraud_status'       => '',
            'order_id'           => 'PAY-ABC123-1',
            'gross_amount'       => '100000.00',
        ]));

        // str_replace would yield 100000; correct is 10000000 (cents)
        $this->assertSame(10000000, $result['amount']);
    }

    public function test_webhook_large_amount_converts_correctly(): void
    {
        $result = $this->service->parseWebhookPayload($this->webhookRequest([
            'transaction_status' => 'settlement',
            'fraud_status'       => '',
            'order_id'           => 'PAY-ABC123-1',
            'gross_amount'       => '1500000.00',
        ]));

        $this->assertSame(150000000, $result['amount']);
    }
}
