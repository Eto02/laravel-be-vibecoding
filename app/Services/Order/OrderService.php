<?php

namespace App\Services\Order;

use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\IdempotencyServiceInterface;
use App\Services\Payment\PaymentService;
use App\DTOs\Order\CheckoutDTO;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderShipped;
use App\Jobs\CancelExpiredOrderJob;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly CacheServiceInterface       $cache,
        private readonly IdempotencyServiceInterface $idempotency,
        private readonly PaymentService              $paymentService,
    ) {}

    /** @return Order[] */
    public function checkout(User $user, CheckoutDTO $data, string $idempotencyKey): array
    {
        $orderIds = $this->idempotency->check(
            "checkout:{$user->id}:{$idempotencyKey}",
            function () use ($user, $data) {
                return $this->processCheckout($user, $data);
            }
        );

        return Order::whereIn('id', (array) $orderIds)
            ->with(['items', 'store:id,name,slug'])
            ->get()
            ->all();
    }

    public function getOrdersForBuyer(User $user, ?string $status = null): LengthAwarePaginator
    {
        return Order::where('user_id', $user->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['store:id,name,slug', 'items'])
            ->latest()
            ->paginate(15);
    }

    public function findForBuyer(User $user, int $orderId): Order
    {
        return Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->with(['items', 'store:id,name,slug', 'statusLogs', 'dispute'])
            ->firstOrFail();
    }

    public function cancelByBuyer(User $user, Order $order): Order
    {
        if (! $order->isOwnedByUser($user->id)) {
            throw new \DomainException('Order does not belong to this user.');
        }

        if (! $order->isPending()) {
            throw new \DomainException('Only pending orders can be cancelled.');
        }

        // Cancel any active gateway charge so the VA/QR is deactivated immediately
        $this->paymentService->cancelPendingPaymentsForOrder($order);

        return $this->cancel($order, $user->id, 'Cancelled by buyer.');
    }

    public function confirmReceived(User $user, Order $order): Order
    {
        if (! $order->isOwnedByUser($user->id)) {
            throw new \DomainException('Order does not belong to this user.');
        }

        if ($order->status !== OrderStatus::Shipped) {
            throw new \DomainException('Only shipped orders can be confirmed as received.');
        }

        $delivered = $this->transition($order, OrderStatus::Delivered, $user->id, 'Received confirmed by buyer.');

        OrderDelivered::dispatch($delivered);

        return $delivered;
    }

    public function completeOrder(User $user, Order $order): Order
    {
        if (! $order->isOwnedByUser($user->id)) {
            throw new \DomainException('Order does not belong to this user.');
        }

        if ($order->status !== OrderStatus::Delivered) {
            throw new \DomainException('Only delivered orders can be marked as completed.');
        }

        $completed = $this->transition($order, OrderStatus::Completed, $user->id, 'Order completed by buyer.');

        OrderCompleted::dispatch($completed);

        return $completed;
    }

    public function getOrdersForMerchant(Store $store, ?string $status = null): LengthAwarePaginator
    {
        return Order::where('store_id', $store->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['user:id,name,email', 'items'])
            ->latest()
            ->paginate(15);
    }

    public function findForMerchant(Store $store, int $orderId): Order
    {
        return Order::where('id', $orderId)
            ->where('store_id', $store->id)
            ->with(['items', 'user:id,name,email', 'statusLogs', 'dispute'])
            ->firstOrFail();
    }

    public function confirmByMerchant(Store $store, Order $order): Order
    {
        if (! $order->isOwnedByStore($store->id)) {
            throw new \DomainException('Order does not belong to this store.');
        }

        if ($order->status !== OrderStatus::Paid) {
            throw new \DomainException('Only paid orders can be confirmed.');
        }

        return $this->transition($order, OrderStatus::Processing, $store->user_id, 'Order confirmed by merchant.');
    }

    public function shipByMerchant(Store $store, Order $order, string $trackingNumber): Order
    {
        if (! $order->isOwnedByStore($store->id)) {
            throw new \DomainException('Order does not belong to this store.');
        }

        if ($order->status !== OrderStatus::Processing) {
            throw new \DomainException('Only processing orders can be shipped.');
        }

        $order->update(['tracking_number' => $trackingNumber]);

        $shipped = $this->transition($order, OrderStatus::Shipped, $store->user_id, "AWB: {$trackingNumber}");

        OrderShipped::dispatch($shipped);

        return $shipped;
    }

    public function createDispute(User $user, Order $order, string $reason, string $description): OrderDispute
    {
        if (! $order->isOwnedByUser($user->id)) {
            throw new \DomainException('Order does not belong to this user.');
        }

        if (! in_array($order->status, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed])) {
            throw new \DomainException('Disputes can only be filed for shipped, delivered, or completed orders.');
        }

        if ($order->dispute()->exists()) {
            throw new \DomainException('A dispute already exists for this order.');
        }

        $dispute = $order->dispute()->create([
            'user_id'     => $user->id,
            'reason'      => $reason,
            'description' => $description,
            'status'      => DisputeStatus::Open,
        ]);

        $this->transition($order, OrderStatus::Disputed, $user->id, 'Dispute filed.');

        return $dispute;
    }

    public function cancel(Order $order, ?int $cancelledBy, string $note = 'Order cancelled.'): Order
    {
        $cancelled = $this->transition($order, OrderStatus::Cancelled, $cancelledBy, $note);

        OrderCancelled::dispatch($cancelled);

        return $cancelled;
    }

    private function processCheckout(User $user, CheckoutDTO $data): array
    {
        // Quick fail before acquiring lock
        $cartExists = \App\Models\Cart::where('user_id', $user->id)
            ->whereHas('items')
            ->exists();

        if (! $cartExists) {
            throw new \DomainException('Cart is empty.');
        }

        $orderIds           = [];
        $checkedOutItemIds  = [];

        DB::transaction(function () use ($user, $data, &$orderIds, &$checkedOutItemIds) {
            // Reload cart with lock inside transaction to prevent race conditions
            $cart = \App\Models\Cart::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items()->count() === 0) {
                throw new \DomainException('Cart is empty.');
            }

            $cart->load([
                'items.variant.product',
                'items.store:id,name',
            ]);

            $cartByStore = $cart->items->groupBy('store_id');

            foreach ($data->items as $checkoutItem) {
                $storeItems = $cartByStore->get($checkoutItem->storeId);

                if (! $storeItems || $storeItems->isEmpty()) {
                    throw new \DomainException("No cart items found for store ID {$checkoutItem->storeId}.");
                }

                // Partial checkout: filter to specific items if item_ids provided
                if ($checkoutItem->itemIds !== null) {
                    $storeItems = $storeItems->whereIn('id', $checkoutItem->itemIds);
                    if ($storeItems->isEmpty()) {
                        throw new \DomainException("None of the specified item IDs belong to store {$checkoutItem->storeId}.");
                    }
                }

                $address = Address::where('id', $checkoutItem->addressId)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $subtotal = $storeItems->sum(fn ($i) => $i->price_snapshot * $i->quantity);
                $total    = $subtotal + $checkoutItem->shippingFee;

                $order = Order::create([
                    'order_number'     => null,
                    'user_id'          => $user->id,
                    'store_id'         => $checkoutItem->storeId,
                    'address_snapshot' => [
                        'recipient_name' => $address->recipient_name,
                        'phone'          => $address->phone,
                        'province'       => $address->province,
                        'city'           => $address->city,
                        'district'       => $address->district,
                        'postal_code'    => $address->postal_code,
                        'street'         => $address->street,
                    ],
                    'subtotal'         => $subtotal,
                    'shipping_fee'     => $checkoutItem->shippingFee,
                    'discount'         => 0,
                    'total'            => $total,
                    'shipping_courier' => $checkoutItem->shippingCourier,
                    'shipping_service' => $checkoutItem->shippingService,
                    'status'           => OrderStatus::Pending,
                    'payment_due_at'   => now()->addHours(24),
                    'notes'            => $checkoutItem->notes,
                ]);

                foreach ($storeItems as $cartItem) {
                    $variant = $cartItem->variant;

                    $affected = DB::table('product_variants')
                        ->where('id', $variant->id)
                        ->where('stock', '>=', $cartItem->quantity)
                        ->decrement('stock', $cartItem->quantity);

                    if ($affected === 0) {
                        $productName = $variant->product->name ?? "Product #{$variant->product_id}";
                        throw new \DomainException("Insufficient stock for {$productName}. Please update your cart.");
                    }

                    $checkedOutItemIds[] = $cartItem->id;

                    $order->items()->create([
                        'product_variant_id' => $variant->id,
                        'product_snapshot'   => [
                            'product_name' => $variant->product->name,
                            'variant_sku'  => $variant->sku,
                            'attributes'   => $variant->attributes,
                            'weight_gram'  => $variant->weight_gram,
                        ],
                        'quantity'           => $cartItem->quantity,
                        'unit_price'         => $cartItem->price_snapshot,
                        'subtotal'           => $cartItem->price_snapshot * $cartItem->quantity,
                    ]);
                }

                $this->logStatusChange($order, null, OrderStatus::Pending->value, $user->id, 'Order placed.');

                $order->update([
                    'order_number' => 'INV/' . now()->format('Y/m') . '/' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                ]);

                $orderIds[] = $order->id;
            }
        });

        // Remove only the checked-out cart items (partial checkout leaves remaining items intact)
        \App\Models\CartItem::whereIn('id', $checkedOutItemIds)->delete();
        $this->cache->forget("cart:user:{$user->id}");

        // Dispatch events outside the DB transaction
        foreach (Order::whereIn('id', $orderIds)->with(['items', 'user', 'store.user'])->get() as $order) {
            OrderPlaced::dispatch($order);
            CancelExpiredOrderJob::dispatch($order)->delay($order->payment_due_at);
        }

        return $orderIds;
    }

    private function transition(Order $order, OrderStatus $newStatus, ?int $changedBy, string $note): Order
    {
        if (! $order->status->canTransitionTo($newStatus)) {
            throw new \DomainException(
                "Cannot transition order from '{$order->status->value}' to '{$newStatus->value}'."
            );
        }

        $fromStatus = $order->status->value;

        $order->update(['status' => $newStatus]);

        $this->logStatusChange($order, $fromStatus, $newStatus->value, $changedBy, $note);

        return $order->fresh(['items', 'statusLogs']);
    }

    private function logStatusChange(Order $order, ?string $from, string $to, ?int $changedBy, ?string $note): void
    {
        $order->statusLogs()->create([
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => $note,
            'changed_by'  => $changedBy,
        ]);
    }
}
