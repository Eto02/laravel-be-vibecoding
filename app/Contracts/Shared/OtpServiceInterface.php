<?php

namespace App\Contracts\Shared;

interface OtpServiceInterface
{
    /**
     * @throws \App\Exceptions\Otp\OtpRateLimitException
     */
    public function generate(string $identifier): string;

    /**
     * @throws \App\Exceptions\Otp\OtpExpiredException
     * @throws \App\Exceptions\Otp\OtpMaxRetryException
     */
    public function verify(string $identifier, string $otp): bool;

    public function invalidate(string $identifier): void;

    public function remainingRetries(string $identifier): int;
}
