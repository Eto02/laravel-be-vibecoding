<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\Payment\ProcessWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        // Signature verification is synchronous — reject invalid webhooks before queuing
        $gateway = app("payment.{$provider}");

        if (! $gateway->verifyWebhook($request)) {
            return ApiResponse::error('Invalid webhook signature.', 403);
        }

        // Dispatch for async processing: dual verification + state update happen in the job
        ProcessWebhookJob::dispatch($provider, $request->all());

        return ApiResponse::success('Webhook received.');
    }
}
