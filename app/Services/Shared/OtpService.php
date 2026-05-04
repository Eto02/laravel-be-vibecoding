<?php

namespace App\Services\Shared;

use App\Contracts\Shared\OtpServiceInterface;
use App\Exceptions\Otp\OtpExpiredException;
use App\Exceptions\Otp\OtpMaxRetryException;
use App\Exceptions\Otp\OtpRateLimitException;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class OtpService implements OtpServiceInterface
{
    private const OTP_TTL       = 300;   // 5 minutes
    private const RATE_TTL      = 600;   // 10 minutes window
    private const MAX_REQUESTS  = 3;
    private const MAX_RETRIES   = 5;
    private const OTP_LENGTH    = 6;

    public function __construct(
        private readonly RedisFactory $redis,
    ) {}

    public function generate(string $identifier): string
    {
        $rateKey = "otp:rate:{$identifier}";
        $count   = (int) $this->redis->connection()->get($rateKey);

        if ($count >= self::MAX_REQUESTS) {
            throw new OtpRateLimitException($identifier);
        }

        $otp  = str_pad((string) random_int(0, 10 ** self::OTP_LENGTH - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);
        $hash = hash('sha256', $otp);

        $this->redis->connection()->pipeline(function ($pipe) use ($identifier, $hash, $rateKey): void {
            $pipe->setex("otp:code:{$identifier}", self::OTP_TTL, $hash);
            $pipe->del("otp:retry:{$identifier}");
            $pipe->incr($rateKey);
            $pipe->expire($rateKey, self::RATE_TTL);
        });

        return $otp;
    }

    public function verify(string $identifier, string $otp): bool
    {
        $storedHash = $this->redis->connection()->get("otp:code:{$identifier}");

        if ($storedHash === null) {
            throw new OtpExpiredException($identifier);
        }

        $retryKey = "otp:retry:{$identifier}";
        $retries  = (int) $this->redis->connection()->get($retryKey);

        if ($retries >= self::MAX_RETRIES) {
            $this->invalidate($identifier);
            throw new OtpMaxRetryException($identifier);
        }

        if (hash_equals($storedHash, hash('sha256', $otp))) {
            $this->invalidate($identifier);
            return true;
        }

        $this->redis->connection()->pipeline(function ($pipe) use ($retryKey): void {
            $pipe->incr($retryKey);
            $pipe->expire($retryKey, self::OTP_TTL);
        });

        return false;
    }

    public function invalidate(string $identifier): void
    {
        $this->redis->connection()->del(
            "otp:code:{$identifier}",
            "otp:retry:{$identifier}",
        );
    }

    public function remainingRetries(string $identifier): int
    {
        $used = (int) $this->redis->connection()->get("otp:retry:{$identifier}");
        return max(0, self::MAX_RETRIES - $used);
    }
}
