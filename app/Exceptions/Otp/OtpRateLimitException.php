<?php

namespace App\Exceptions\Otp;

use RuntimeException;

class OtpRateLimitException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("OTP rate limit exceeded for [{$identifier}]. Try again later.");
    }
}
