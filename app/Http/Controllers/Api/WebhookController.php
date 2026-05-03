<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function xendit(Request $request): JsonResponse
    {
        try {
            $this->paymentService->handleWebhook($request);

            return ApiResponse::success('Webhook processed successfully.');
        } catch (\RuntimeException $e) {
            $status = $e->getCode() === 403 ? 403 : 400;

            \Illuminate\Support\Facades\Log::warning("Webhook Runtime Error: " . $e->getMessage());

            return ApiResponse::error($e->getMessage(), $status);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Webhook Critical Error: " . $e->getMessage(), [
                'exception' => $e
            ]);

            return ApiResponse::error('Failed to process webhook.', 500);
        }
    }
}
