<?php

namespace App\Services\Shared;

use App\Contracts\Shared\SmsServiceInterface;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

class SmsService implements SmsServiceInterface
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function send(string $to, string $message): void
    {
        $provider = config('services.sms.provider', 'log');

        match ($provider) {
            'fonnte' => $this->sendViaFonnte($to, $message),
            'log'    => $this->sendViaLog($to, $message),
            default  => $this->sendViaLog($to, $message),
        };
    }

    public function sendOtp(string $to, string $otp): void
    {
        $this->send($to, "Your verification code is: {$otp}. Valid for 5 minutes. Do not share this code.");
    }

    private function sendViaFonnte(string $to, string $message): void
    {
        $this->http->withToken(config('services.sms.fonnte_token'))
            ->post('https://api.fonnte.com/send', [
                'target'  => $to,
                'message' => $message,
            ]);
    }

    private function sendViaLog(string $to, string $message): void
    {
        Log::channel('stack')->info('SMS (log driver)', [
            'to'      => $to,
            'message' => $message,
        ]);
    }
}
