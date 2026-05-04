<?php

namespace App\Exceptions\Otp;

use RuntimeException;

class OtpExpiredException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("OTP for [{$identifier}] has expired.");
    }
}
