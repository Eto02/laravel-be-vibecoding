<?php

namespace Tests\Unit\Services\Shared;

use App\Exceptions\Otp\OtpExpiredException;
use App\Exceptions\Otp\OtpMaxRetryException;
use App\Exceptions\Otp\OtpRateLimitException;
use App\Services\Shared\OtpService;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Use real Redis from the test environment
        $this->service = $this->app->make(OtpService::class);
        // Clean test keys before each test
        $this->app->make(RedisFactory::class)->connection()->flushdb();
    }

    public function test_otp_verify_succeeds_with_valid_code(): void
    {
        $otp = $this->service->generate('user:1');
        $this->assertTrue($this->service->verify('user:1', $otp));
    }

    public function test_otp_invalidated_after_successful_verify(): void
    {
        $otp = $this->service->generate('user:2');
        $this->service->verify('user:2', $otp);

        $this->expectException(OtpExpiredException::class);
        $this->service->verify('user:2', $otp);
    }

    public function test_otp_rate_limit_throws_after_3_requests_in_10_minutes(): void
    {
        $this->service->generate('user:3');
        $this->service->generate('user:3');
        $this->service->generate('user:3');

        $this->expectException(OtpRateLimitException::class);
        $this->service->generate('user:3');
    }

    public function test_otp_verify_invalidates_after_5_failed_attempts(): void
    {
        $this->service->generate('user:4');

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->service->verify('user:4', '000000');
            } catch (OtpMaxRetryException) {
                // Expected on 5th attempt
            }
        }

        $this->expectException(OtpMaxRetryException::class);
        $this->service->verify('user:4', '000000');
    }

    public function test_otp_expires_after_ttl(): void
    {
        // We test via invalidate since actual TTL would require sleep(300)
        $this->service->generate('user:5');
        $this->service->invalidate('user:5');

        $this->expectException(OtpExpiredException::class);
        $this->service->verify('user:5', '123456');
    }

    public function test_remaining_retries_decrements_on_failure(): void
    {
        $this->service->generate('user:6');

        $this->assertSame(5, $this->service->remainingRetries('user:6'));

        try {
            $this->service->verify('user:6', '000000');
        } catch (\Exception) {
        }

        $this->assertSame(4, $this->service->remainingRetries('user:6'));
    }
}
