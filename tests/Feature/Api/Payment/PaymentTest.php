<?php

namespace Tests\Feature\Api\Payment;

use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create([
            'role'              => UserRole::Buyer,
            'email_verified_at' => now(),
        ]);
    }

    private function pendingOrder(User $buyer): Order
    {
        $merchant = User::factory()->create(['role' => UserRole::Merchant, 'email_verified_at' => now()]);
        $store    = Store::factory()->create([
            'user_id'    => $merchant->id,
            'status'     => MerchantStatus::Active,
            'kyc_status' => KycStatus::Approved,
        ]);
        $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5, 'price' => 10000000]);

        return Order::factory()->create([
            'user_id'  => $buyer->id,
            'store_id' => $store->id,
            'status'   => OrderStatus::Pending,
            'total'    => 10000000,
        ]);
    }

    private function mockGateway(array $result = []): void
    {
        $default = [
            'gateway_ref'     => 'gw-ref-123',
            'redirect_url'    => 'https://gateway.test/pay',
            'payment_details' => ['bank_code' => 'BCA', 'account_number' => '88081234567'],
            'expires_at'      => now()->addHours(24)->toISOString(),
        ];

        $mock = $this->createMock(PaymentGatewayInterface::class);
        $mock->method('createCharge')->willReturn(array_merge($default, $result));
        $mock->method('verifyWebhook')->willReturn(true);
        $mock->method('parseWebhookPayload')->willReturn([
            'event'       => 'payment.succeeded',
            'external_id' => 'PAY-TEST-123',
            'status'      => 'paid',
            'amount'      => 10000000,
        ]);
        $mock->method('refundPayment')->willReturn(['id' => 'ref-123']);

        $this->app->instance(PaymentGatewayInterface::class, $mock);
    }

    // ── initiate ──────────────────────────────────────────────────────────────

    public function test_buyer_can_initiate_va_payment(): void
    {
        Event::fake();
        $this->mockGateway();
        $buyer = $this->buyer();
        $order = $this->pendingOrder($buyer);

        $response = $this->actingAs($buyer)->postJson('/api/payments/initiate', [
            'order_id'  => $order->id,
            'gateway'   => 'xendit',
            'method'    => 'virtual_account',
            'bank_code' => 'BCA',
        ], ['X-Idempotency-Key' => 'test-key-1']);

        $response->assertStatus(201)->assertJsonStructure([
            'success', 'message', 'data' => ['id', 'order_id', 'gateway', 'method', 'amount_cents', 'amount', 'status', 'payment_details'],
        ]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'virtual_account']);
    }

    public function test_initiate_returns_422_for_wrong_order(): void
    {
        $buyer       = $this->buyer();
        $otherBuyer  = $this->buyer();
        $order       = $this->pendingOrder($otherBuyer);

        $response = $this->actingAs($buyer)->postJson('/api/payments/initiate', [
            'order_id' => $order->id,
            'gateway'  => 'xendit',
            'method'   => 'virtual_account',
            'bank_code' => 'BCA',
        ]);

        $response->assertStatus(422);
    }

    public function test_initiate_requires_auth(): void
    {
        $response = $this->postJson('/api/payments/initiate', [
            'order_id' => 1,
            'gateway'  => 'xendit',
            'method'   => 'virtual_account',
        ]);

        $response->assertStatus(401);
    }

    public function test_ewallet_requires_ewallet_type(): void
    {
        $buyer = $this->buyer();
        $order = $this->pendingOrder($buyer);

        $response = $this->actingAs($buyer)->postJson('/api/payments/initiate', [
            'order_id' => $order->id,
            'gateway'  => 'xendit',
            'method'   => 'ewallet',
            // missing ewallet_type
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['ewallet_type']);
    }

    public function test_ovo_requires_phone(): void
    {
        $buyer = $this->buyer();
        $order = $this->pendingOrder($buyer);

        $response = $this->actingAs($buyer)->postJson('/api/payments/initiate', [
            'order_id'    => $order->id,
            'gateway'     => 'xendit',
            'method'      => 'ewallet',
            'ewallet_type' => 'OVO',
            // missing phone
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    // ── status ────────────────────────────────────────────────────────────────

    public function test_buyer_can_get_payment_status(): void
    {
        $buyer   = $this->buyer();
        $order   = $this->pendingOrder($buyer);
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $response = $this->actingAs($buyer)->getJson("/api/payments/{$payment->id}/status");

        $response->assertStatus(200)->assertJsonStructure([
            'success', 'data' => ['id', 'status', 'amount_cents'],
        ]);
    }

    public function test_status_requires_auth(): void
    {
        $payment = Payment::factory()->create();

        $this->getJson("/api/payments/{$payment->id}/status")->assertStatus(401);
    }

    // ── refund ────────────────────────────────────────────────────────────────

    public function test_buyer_can_refund_paid_payment(): void
    {
        Event::fake();
        $this->mockGateway();
        $buyer   = $this->buyer();
        $order   = $this->pendingOrder($buyer);
        $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 10000000]);

        $response = $this->actingAs($buyer)->postJson("/api/payments/{$payment->id}/refund", [
            'reason' => 'Item not received',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['data' => ['id', 'status', 'amount_cents']]);
        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id]);
    }

    public function test_buyer_cannot_refund_other_users_payment(): void
    {
        $buyer       = $this->buyer();
        $otherBuyer  = $this->buyer();
        $order       = $this->pendingOrder($otherBuyer);
        $payment     = Payment::factory()->paid()->create(['order_id' => $order->id]);

        $response = $this->actingAs($buyer)->postJson("/api/payments/{$payment->id}/refund", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(404);
    }
}
