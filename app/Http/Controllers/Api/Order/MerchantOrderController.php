<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ShipOrderRequest;
use App\Http\Resources\Order\OrderListResource;
use App\Http\Resources\Order\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $store  = $request->user()->store;
        $orders = $this->orderService->getOrdersForMerchant(
            $store,
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
        $store = $request->user()->store;
        $order = $this->orderService->findForMerchant($store, $id);

        return ApiResponse::success('Order retrieved.', new OrderResource($order));
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->store;
        $order = $this->orderService->findForMerchant($store, $id);
        $order = $this->orderService->confirmByMerchant($store, $order);

        return ApiResponse::success('Order confirmed.', new OrderResource($order));
    }

    public function ship(ShipOrderRequest $request, int $id): JsonResponse
    {
        $store = $request->user()->store;
        $order = $this->orderService->findForMerchant($store, $id);
        $order = $this->orderService->shipByMerchant($store, $order, $request->input('tracking_number'));

        return ApiResponse::success('Order marked as shipped.', new OrderResource($order));
    }
}
