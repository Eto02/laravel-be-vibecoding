<?php

namespace Tests\Feature\Api\Order;

use App\Contracts\Shared\IdempotencyServiceInterface;
use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Jobs\CancelExpiredOrderJob;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buyer(): User
    {
        return User::factory()->create([
            'role'              => UserRole::Buyer,
            'email_verified_at' => now(),
        ]);
    }

    private function merchantWithStore(): array
    {
        $merchant = User::factory()->create([
            'role'              => UserRole::Merchant,
            'email_verified_at' => now(),
        ]);
        $store = Store::factory()->create([
            'user_id'    => $merchant->id,
            'status'     => MerchantStatus::Active,
            'kyc_status' => KycStatus::Approved,
        ]);

        return [$merchant, $store];
    }

    private function activeVariant(Store $store, int $stock = 10, int $price = 15000000): ProductVariant
    {
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Active]);

        return ProductVariant::factory()->for($product)->create([
            'stock' => $stock,
            'price' => $price,
        ]);
    }

    private function addressFor(User $user): Address
    {
        return Address::factory()->create(['user_id' => $user->id]);
    }

    private function addToCart(User $user, ProductVariant $variant, int $qty = 1): CartItem
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        return $cart->items()->create([
            'product_variant_id' => $variant->id,
            'product_id'         => $variant->product_id,
            'store_id'           => $variant->product->store_id,
            'quantity'           => $qty,
            'price_snapshot'     => $variant->price,
        ]);
    }

    private function passthroughIdempotency(): void
    {
        $this->app->bind(IdempotencyServiceInterface::class, fn () => new class implements IdempotencyServiceInterface {
            public function check(string $key, callable $callback, int $ttl = 86400): mixed
            {
                return $callback();
            }
        });
    }

    private function checkoutPayload(int $storeId, int $addressId, array $extra = []): array
    {
        return array_merge([
            'items' => [[
                'store_id'         => $storeId,
                'address_id'       => $addressId,
                'shipping_courier' => 'jne',
                'shipping_service' => 'REG',
                'shipping_fee'     => 1500000,
                'notes'            => null,
            ]],
            'voucher_code' => null,
        ], $extra);
    }

    private function pendingOrderFor(User $buyer, Store $store): Order
    {
        $order = Order::factory()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
            'status'   => OrderStatus::Pending,
        ]);
        $order->update([
            'order_number' => 'INV/2026/05/' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return $order;
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_cannot_checkout(): void
    {
        $this->postJson('/api/orders/checkout')->assertStatus(401);
    }

    // ── Checkout ──────────────────────────────────────────────────────────────

    public function test_user_can_checkout_and_creates_order_per_store(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);
        $this->passthroughIdempotency();

        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $variant = $this->activeVariant($store, 10);
        $address = $this->addressFor($buyer);
        $this->addToCart($buyer, $variant, 2);

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'test-key-001'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(201)
            ->assertJsonStructure(['data' => [['id', 'order_number', 'status', 'total_cents', 'items']]]);

        $this->assertDatabaseHas('orders', [
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
            'status'   => 'pending',
        ]);
    }

    public function test_checkout_decrements_variant_stock(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);
        $this->passthroughIdempotency();

        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $variant = $this->activeVariant($store, 10);
        $address = $this->addressFor($buyer);
        $this->addToCart($buyer, $variant, 3);

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'test-key-002'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(201);

        $this->assertDatabaseHas('product_variants', [
            'id'    => $variant->id,
            'stock' => 7,
        ]);
    }

    public function test_checkout_clears_cart_after_success(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);
        $this->passthroughIdempotency();

        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $variant = $this->activeVariant($store, 10);
        $address = $this->addressFor($buyer);
        $this->addToCart($buyer, $variant, 1);

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'test-key-003'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(201);

        $cart = Cart::where('user_id', $buyer->id)->first();
        $this->assertEquals(0, $cart->items()->count());
    }

    public function test_checkout_fails_if_stock_insufficient(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);
        $this->passthroughIdempotency();

        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $variant = $this->activeVariant($store, 2);
        $address = $this->addressFor($buyer);
        $this->addToCart($buyer, $variant, 5);

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'test-key-004'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(422);
    }

    public function test_checkout_fails_if_cart_empty(): void
    {
        $this->passthroughIdempotency();

        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $address = $this->addressFor($buyer);

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'test-key-005'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(422);
    }

    public function test_checkout_requires_idempotency_key(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $address = $this->addressFor($buyer);

        $this->actingAs($buyer)
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(422)
            ->assertJsonPath('errors._idempotency_key.0', 'The X-Idempotency-Key header is required.');
    }

    public function test_duplicate_checkout_with_same_key_returns_cached_result(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);

        $callCount = 0;
        $this->app->bind(IdempotencyServiceInterface::class, fn () => new class($callCount) implements IdempotencyServiceInterface {
            private static array $store = [];

            public function check(string $key, callable $callback, int $ttl = 86400): mixed
            {
                if (array_key_exists($key, static::$store)) {
                    return static::$store[$key];
                }
                return static::$store[$key] = $callback();
            }
        });

        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $variant = $this->activeVariant($store, 10);
        $address = $this->addressFor($buyer);
        $this->addToCart($buyer, $variant, 1);

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'dup-key-001'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(201);

        // Second call with same key — no new order should be created
        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'dup-key-001'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(201);

        $this->assertEquals(1, Order::where('user_id', $buyer->id)->count());
    }

    public function test_checkout_with_multiple_items_from_same_store(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);
        $this->passthroughIdempotency();

        $buyer    = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $address  = $this->addressFor($buyer);

        $variantA = $this->activeVariant($store, 5);
        $variantB = $this->activeVariant($store, 3);

        $this->addToCart($buyer, $variantA, 2); // buy 2 of A
        $this->addToCart($buyer, $variantB, 1); // buy 1 of B

        $response = $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'multi-item-001'])
            ->postJson('/api/orders/checkout', $this->checkoutPayload($store->id, $address->id))
            ->assertStatus(201);

        $order = Order::where('user_id', $buyer->id)->first();

        // Both items in a single order
        $this->assertCount(2, $order->items);

        // Subtotal = price*qty for each item
        $expectedSubtotal = ($variantA->price * 2) + ($variantB->price * 1);
        $this->assertEquals($expectedSubtotal, $order->subtotal);

        // Cart fully cleared
        $cart = Cart::where('user_id', $buyer->id)->first();
        $this->assertEquals(0, $cart->items()->count());

        // Stock decremented per quantity
        $this->assertEquals(3, $variantA->fresh()->stock); // 5 - 2
        $this->assertEquals(2, $variantB->fresh()->stock); // 3 - 1
    }

    public function test_partial_checkout_only_checks_out_selected_items(): void
    {
        Queue::fake([CancelExpiredOrderJob::class]);
        $this->passthroughIdempotency();

        $buyer   = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $address = $this->addressFor($buyer);

        $variantA = $this->activeVariant($store, 5);
        $variantB = $this->activeVariant($store, 5);
        $variantC = $this->activeVariant($store, 5);

        $itemA = $this->addToCart($buyer, $variantA, 1);
        $itemB = $this->addToCart($buyer, $variantB, 1);
        $itemC = $this->addToCart($buyer, $variantC, 1);

        // Checkout only item C
        $payload = $this->checkoutPayload($store->id, $address->id);
        $payload['items'][0]['item_ids'] = [$itemC->id];

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'partial-key-001'])
            ->postJson('/api/orders/checkout', $payload)
            ->assertStatus(201);

        // Order has only item C
        $order = Order::where('user_id', $buyer->id)->first();
        $this->assertCount(1, $order->items);

        // Cart still has items A and B
        $cart = Cart::where('user_id', $buyer->id)->first();
        $remaining = $cart->items()->pluck('id')->toArray();
        $this->assertContains($itemA->id, $remaining);
        $this->assertContains($itemB->id, $remaining);
        $this->assertNotContains($itemC->id, $remaining);
    }

    public function test_partial_checkout_fails_with_invalid_item_ids(): void
    {
        $this->passthroughIdempotency();

        $buyer   = $this->buyer();
        [, $store]      = $this->merchantWithStore();
        [, $otherStore] = $this->merchantWithStore();
        $address = $this->addressFor($buyer);

        $variantA    = $this->activeVariant($store, 5);
        $variantOther = $this->activeVariant($otherStore, 5);
        $this->addToCart($buyer, $variantA, 1);
        $itemOther = $this->addToCart($buyer, $variantOther, 1);

        // Try to checkout item from otherStore under store's store_id
        $payload = $this->checkoutPayload($store->id, $address->id);
        $payload['items'][0]['item_ids'] = [$itemOther->id];

        $this->actingAs($buyer)
            ->withHeaders(['X-Idempotency-Key' => 'partial-key-002'])
            ->postJson('/api/orders/checkout', $payload)
            ->assertStatus(422);
    }

    // ── Buyer: list & show ────────────────────────────────────────────────────

    public function test_user_can_view_own_orders(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $this->pendingOrderFor($buyer, $store);

        $this->actingAs($buyer)
            ->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'order_number', 'status', 'total_cents']]]);
    }

    public function test_user_cannot_view_other_users_order(): void
    {
        $buyer1 = $this->buyer();
        $buyer2 = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $order = $this->pendingOrderFor($buyer1, $store);

        $this->actingAs($buyer2)
            ->getJson("/api/orders/{$order->id}")
            ->assertStatus(404);
    }

    // ── Buyer: cancel ─────────────────────────────────────────────────────────

    public function test_buyer_can_cancel_pending_order(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $order = $this->pendingOrderFor($buyer, $store);

        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_buyer_cannot_cancel_paid_order(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $order = Order::factory()->paid()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    // ── Buyer: confirm received ───────────────────────────────────────────────

    public function test_buyer_can_confirm_received(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $order = Order::factory()->shipped()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->id}/receive")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'delivered');
    }

    // ── Merchant: list & confirm & ship ──────────────────────────────────────

    public function test_merchant_can_confirm_order(): void
    {
        [$merchant, $store] = $this->merchantWithStore();
        $buyer = $this->buyer();
        $order = Order::factory()->paid()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($merchant)
            ->putJson("/api/merchant/orders/{$order->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_merchant_can_ship_order_with_tracking_number(): void
    {
        [$merchant, $store] = $this->merchantWithStore();
        $buyer = $this->buyer();
        $order = Order::factory()->processing()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($merchant)
            ->putJson("/api/merchant/orders/{$order->id}/ship", ['tracking_number' => 'JNE-123456789'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'shipped');

        $this->assertDatabaseHas('orders', [
            'id'              => $order->id,
            'tracking_number' => 'JNE-123456789',
        ]);
    }

    public function test_merchant_cannot_access_other_stores_order(): void
    {
        [$merchant1, $store1] = $this->merchantWithStore();
        [, $store2] = $this->merchantWithStore();
        $buyer = $this->buyer();
        $order = Order::factory()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store2->id,
        ]);

        $this->actingAs($merchant1)
            ->getJson("/api/merchant/orders/{$order->id}")
            ->assertStatus(404);
    }

    // ── Buyer: dispute ────────────────────────────────────────────────────────

    public function test_buyer_can_create_dispute(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $order = Order::factory()->shipped()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->id}/disputes", [
                'reason'      => 'Item not as described',
                'description' => 'The product received is completely different from the listing photo and description.',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'reason', 'status']]);

        $this->assertDatabaseHas('order_disputes', [
            'order_id' => $order->id,
            'user_id'  => $buyer->id,
        ]);
    }

    // ── Stock restore on cancel ───────────────────────────────────────────────

    public function test_cancelled_order_restores_stock(): void
    {
        $buyer = $this->buyer();
        [, $store] = $this->merchantWithStore();
        $variant = $this->activeVariant($store, 10);

        // Create pending order with 1 item (qty=3) directly — no checkout needed
        $order = $this->pendingOrderFor($buyer, $store);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'product_snapshot'   => ['product_name' => 'Test Product', 'variant_sku' => 'SKU-TEST'],
            'quantity'           => 3,
            'unit_price'         => 15000000,
            'subtotal'           => 45000000,
        ]);

        // Simulate stock already decremented by checkout
        \Illuminate\Support\Facades\DB::table('product_variants')
            ->where('id', $variant->id)
            ->update(['stock' => 7]);

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 7]);

        // Cancel the order — RestoreProductStock listener should fire
        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(200);

        // Stock should be restored to 10
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 10]);
    }
}
