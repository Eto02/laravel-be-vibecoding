<?php

namespace App\Http\Controllers\Api\Payment;

use App\DTOs\Payment\InitiatePaymentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Http\Resources\Payment\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->initiatePayment(
            InitiatePaymentDTO::fromRequest($request)
        );

        return ApiResponse::success('Payment initiated successfully.', new PaymentResource($payment), 201);
    }

    public function status(int $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $payment = $this->paymentService->getStatus($payment);

        return ApiResponse::success('Payment status retrieved.', new PaymentResource($payment));
    }

    public function refund(RefundPaymentRequest $request, int $id): JsonResponse
    {
        $payment = Payment::where('id', $id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $refund = $this->paymentService->requestRefund($payment, $request->input('reason', ''));

        return ApiResponse::success('Refund processed successfully.', new RefundResource($refund));
    }
}
