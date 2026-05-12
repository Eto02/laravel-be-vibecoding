<?php

namespace Tests\Unit\Services\Payment;

use App\Services\Payment\XenditPaymentService;
use Tests\TestCase;

class XenditParseStatusResponseTest extends TestCase
{
    private XenditPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['xendit.secret_key'    => 'test-key']);
        config(['xendit.webhook_token' => 'test-token']);
        config(['xendit.base_url'      => 'https://api.xendit.co']);
        $this->service = new XenditPaymentService();
    }

    public function test_paid_status_normalizes_correctly(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'PAID', 'paid_amount' => 100000, 'amount' => 100000]);

        $this->assertSame('paid', $result['status']);
        $this->assertSame(10000000, $result['amount']);
    }

    public function test_settled_status_normalizes_to_paid(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'SETTLED', 'paid_amount' => 50000, 'amount' => 50000]);

        $this->assertSame('paid', $result['status']);
    }

    public function test_expired_status_normalizes_correctly(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'EXPIRED', 'amount' => 50000]);

        $this->assertSame('expired', $result['status']);
    }

    public function test_pending_status_normalizes_correctly(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'PENDING', 'amount' => 50000]);

        $this->assertSame('pending', $result['status']);
    }

    public function test_unknown_status_normalizes_to_failed(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'VOIDED', 'amount' => 50000]);

        $this->assertSame('failed', $result['status']);
    }

    public function test_amount_falls_back_to_amount_when_paid_amount_absent(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'PAID', 'amount' => 75000]);

        $this->assertSame(7500000, $result['amount']);
    }

    public function test_case_insensitive_status_matching(): void
    {
        $result = $this->service->parseStatusResponse(['status' => 'paid', 'amount' => 10000]);

        $this->assertSame('paid', $result['status']);
    }
}
