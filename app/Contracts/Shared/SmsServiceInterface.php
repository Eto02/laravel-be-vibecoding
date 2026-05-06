<?php

namespace App\Contracts\Shared;

interface SmsServiceInterface
{
    public function send(string $to, string $message): void;

    public function sendOtp(string $to, string $otp): void;
}
