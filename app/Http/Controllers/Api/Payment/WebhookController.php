<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function xendit(Request $request): JsonResponse
    {
        try {
            $this->paymentService->handleWebhook($request);

            return ApiResponse::success('Webhook processed successfully.');
        } catch (\RuntimeException $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            Log::warning('Webhook runtime error: ' . $e->getMessage());

            return ApiResponse::error($e->getMessage(), $status);
        } catch (\Exception $e) {
            Log::error('Webhook critical error: ' . $e->getMessage(), ['exception' => $e]);

            return ApiResponse::error('Failed to process webhook.', 500);
        }
    }
}
