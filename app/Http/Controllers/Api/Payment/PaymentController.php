<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\Payment\TransactionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            $transaction = $this->paymentService->createInvoice($request->validated());

            return ApiResponse::success('Invoice created successfully.', new TransactionResource($transaction), 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create payment invoice.', 500);
        }
    }
}
