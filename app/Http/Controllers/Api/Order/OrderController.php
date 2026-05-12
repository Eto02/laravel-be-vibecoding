<?php

namespace App\Http\Controllers\Api\Order;

use App\DTOs\Order\CheckoutDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Requests\Order\StoreDisputeRequest;
use App\Http\Resources\Order\OrderDisputeResource;
use App\Http\Resources\Order\OrderListResource;
use App\Http\Resources\Order\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $orders = $this->orderService->checkout(
            $request->user(),
            CheckoutDTO::fromRequest($request),
            $request->header('X-Idempotency-Key'),
        );

        return ApiResponse::success('Checkout successful.', OrderResource::collection($orders), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getOrdersForBuyer(
            $request->user(),
            $request->query('status'),
        );

        return ApiResponse::success('Orders retrieved.', OrderListResource::collection($orders), 200, [
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'per_page'     => $orders->perPage(),
            'total'        => $orders->total(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->findForBuyer($request->user(), $id);

        return ApiResponse::success('Order retrieved.', new OrderResource($order));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->findForBuyer($request->user(), $id);
        $order = $this->orderService->cancelByBuyer($request->user(), $order);

        return ApiResponse::success('Order cancelled.', new OrderResource($order));
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->findForBuyer($request->user(), $id);
        $order = $this->orderService->confirmReceived($request->user(), $order);

        return ApiResponse::success('Order confirmed as received.', new OrderResource($order));
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->findForBuyer($request->user(), $id);
        $order = $this->orderService->completeOrder($request->user(), $order);

        return ApiResponse::success('Order marked as completed.', new OrderResource($order));
    }

    public function dispute(StoreDisputeRequest $request, int $id): JsonResponse
    {
        $order   = $this->orderService->findForBuyer($request->user(), $id);
        $dispute = $this->orderService->createDispute(
            $request->user(),
            $order,
            $request->input('reason'),
            $request->input('description'),
        );

        return ApiResponse::success('Dispute submitted.', new OrderDisputeResource($dispute), 201);
    }
}
