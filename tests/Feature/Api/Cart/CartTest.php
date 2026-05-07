<?php

namespace Tests\Feature\Api\Cart;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function activeVariant(int $stock = 10): ProductVariant
    {
        $store   = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Active]);
        return ProductVariant::factory()->for($product)->create(['stock' => $stock, 'price' => 150000]);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_cart(): void
    {
        $this->getJson('/api/cart')->assertStatus(401);
    }

    // ── Add to Cart ───────────────────────────────────────────────────────────

    public function test_authenticated_user_can_add_item_to_cart(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant();

        $this->actingAs($user)
            ->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['total_items', 'total_price_cents', 'stores']]);

        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $variant->id,
            'quantity'           => 2,
        ]);
    }

    public function test_adding_same_variant_increments_quantity(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant(20);

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 3]);

        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $variant->id,
            'quantity'           => 5,
        ]);
        $this->assertEquals(1, CartItem::where('product_variant_id', $variant->id)->count());
    }

    public function test_cannot_add_item_exceeding_stock(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant(3);

        $this->actingAs($user)
            ->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 5])
            ->assertStatus(422);
    }

    public function test_cannot_add_inactive_product_to_cart(): void
    {
        $user    = $this->actingUser();
        $store   = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Draft]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 10, 'price' => 100000]);

        $this->actingAs($user)
            ->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertStatus(422);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_user_can_update_cart_item_quantity(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant(10);

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 2]);
        $item = CartItem::where('product_variant_id', $variant->id)->first();

        $this->actingAs($user)
            ->putJson("/api/cart/items/{$item->id}", ['quantity' => 4])
            ->assertStatus(200);

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 4]);
    }

    public function test_update_quantity_to_zero_returns_422(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant();

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 2]);
        $item = CartItem::where('product_variant_id', $variant->id)->first();

        $this->actingAs($user)
            ->putJson("/api/cart/items/{$item->id}", ['quantity' => 0])
            ->assertStatus(422);
    }

    // ── Remove ────────────────────────────────────────────────────────────────

    public function test_user_can_remove_cart_item(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant();

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);
        $item = CartItem::where('product_variant_id', $variant->id)->first();

        $this->actingAs($user)
            ->deleteJson("/api/cart/items/{$item->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_user_can_clear_cart(): void
    {
        $user     = $this->actingUser();
        $variant1 = $this->activeVariant();
        $variant2 = $this->activeVariant();

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant1->id, 'quantity' => 1]);
        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant2->id, 'quantity' => 1]);

        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertEquals(2, $cart->items()->count());

        $this->actingAs($user)
            ->deleteJson('/api/cart')
            ->assertStatus(204);

        $this->assertEquals(0, $cart->fresh()->items()->count());
    }

    // ── Grouping ──────────────────────────────────────────────────────────────

    public function test_cart_is_grouped_by_store(): void
    {
        $user     = $this->actingUser();
        $variant1 = $this->activeVariant();
        $variant2 = $this->activeVariant();

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant1->id, 'quantity' => 1]);
        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant2->id, 'quantity' => 1]);

        $response = $this->actingAs($user)
            ->getJson('/api/cart')
            ->assertStatus(200);

        $stores = $response->json('data.stores');
        $this->assertCount(2, $stores);
    }

    // ── Soft-deleted product ──────────────────────────────────────────────────

    public function test_soft_deleted_product_marked_unavailable_in_cart(): void
    {
        $user    = $this->actingUser();
        $variant = $this->activeVariant();

        $this->actingAs($user)->postJson('/api/cart/items', ['variant_id' => $variant->id, 'quantity' => 1]);

        $variant->product->delete();

        $response = $this->actingAs($user)
            ->getJson('/api/cart')
            ->assertStatus(200);

        $item = $response->json('data.stores.0.items.0');
        $this->assertFalse($item['is_available']);
        $this->assertNotNull($item['unavailable_reason']);
    }
}
