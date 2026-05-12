<?php

namespace App\Http\Controllers\Api\Payment;

use App\DTOs\Payment\InitiatePaymentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\SwitchPaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Http\Resources\Payment\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function status(Request $request, int $id): JsonResponse
    {
        $payment = $this->paymentService->findForUser($request->user(), $id);
        $payment = $this->paymentService->getStatus($payment);

        return ApiResponse::success('Payment status retrieved.', new PaymentResource($payment));
    }

    public function switch(SwitchPaymentRequest $request, int $id): JsonResponse
    {
        $payment    = $this->paymentService->findForUser($request->user(), $id);
        $newPayment = $this->paymentService->switchPayment(
            $payment,
            InitiatePaymentDTO::fromSwitchRequest($request, $payment->order_id)
        );

        return ApiResponse::success('Payment method switched.', new PaymentResource($newPayment), 201);
    }

    public function refund(RefundPaymentRequest $request, int $id): JsonResponse
    {
        $payment = $this->paymentService->findForUser($request->user(), $id);
        $refund  = $this->paymentService->requestRefund($payment, $request->input('reason', ''));

        return ApiResponse::success('Refund processed successfully.', new RefundResource($refund));
    }
}
