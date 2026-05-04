<?php

namespace App\Exceptions\Otp;

use RuntimeException;

class OtpMaxRetryException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("OTP invalidated for [{$identifier}] after too many failed attempts.");
    }
}
