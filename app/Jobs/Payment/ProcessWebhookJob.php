<?php

namespace App\Jobs\Payment;

use App\Services\Payment\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(
        private readonly string $provider,
        private readonly array  $payload,
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        $request = Request::create(
            uri: '/',
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->payload),
        );

        $paymentService->handleWebhook($request, $this->provider);
    }
}
