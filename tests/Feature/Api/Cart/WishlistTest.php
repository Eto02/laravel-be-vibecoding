<?php

namespace Tests\Feature\Api\Cart;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function activeProduct(): Product
    {
        $store = Store::factory()->create();
        return Product::factory()->for($store)->create(['status' => ProductStatus::Active]);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_wishlist(): void
    {
        $this->getJson('/api/wishlist')->assertStatus(401);
    }

    // ── Add ───────────────────────────────────────────────────────────────────

    public function test_user_can_add_product_to_wishlist(): void
    {
        $user    = $this->actingUser();
        $product = $this->activeProduct();

        $this->actingAs($user)
            ->postJson('/api/wishlist/items', ['product_id' => $product->id])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['total', 'items']]);

        $this->assertDatabaseHas('wishlist_items', ['product_id' => $product->id]);
    }

    public function test_cannot_add_same_product_twice_to_wishlist(): void
    {
        $user    = $this->actingUser();
        $product = $this->activeProduct();

        $this->actingAs($user)->postJson('/api/wishlist/items', ['product_id' => $product->id]);

        $this->actingAs($user)
            ->postJson('/api/wishlist/items', ['product_id' => $product->id])
            ->assertStatus(422);
    }

    // ── Remove ────────────────────────────────────────────────────────────────

    public function test_user_can_remove_product_from_wishlist(): void
    {
        $user    = $this->actingUser();
        $product = $this->activeProduct();

        $this->actingAs($user)->postJson('/api/wishlist/items', ['product_id' => $product->id]);

        $this->actingAs($user)
            ->deleteJson("/api/wishlist/items/{$product->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('wishlist_items', ['product_id' => $product->id]);
    }

    // ── Check ─────────────────────────────────────────────────────────────────

    public function test_wishlist_check_returns_true_if_product_wishlisted(): void
    {
        $user    = $this->actingUser();
        $product = $this->activeProduct();

        $this->actingAs($user)->postJson('/api/wishlist/items', ['product_id' => $product->id]);

        $this->actingAs($user)
            ->getJson("/api/wishlist/items/{$product->id}/check")
            ->assertStatus(200)
            ->assertJsonPath('data.is_wishlisted', true);
    }

    public function test_wishlist_check_returns_false_if_not_wishlisted(): void
    {
        $user    = $this->actingUser();
        $product = $this->activeProduct();

        $this->actingAs($user)
            ->getJson("/api/wishlist/items/{$product->id}/check")
            ->assertStatus(200)
            ->assertJsonPath('data.is_wishlisted', false);
    }

    // ── Product detail integration ────────────────────────────────────────────

    public function test_product_detail_includes_is_wishlisted_when_authenticated(): void
    {
        $user    = $this->actingUser();
        $product = $this->activeProduct();

        $this->actingAs($user)->postJson('/api/wishlist/items', ['product_id' => $product->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/products/{$product->slug}")
            ->assertStatus(200);

        $this->assertTrue($response->json('data.is_wishlisted'));
    }

    public function test_product_detail_omits_is_wishlisted_for_guest(): void
    {
        $product = $this->activeProduct();

        $response = $this->getJson("/api/products/{$product->slug}")
            ->assertStatus(200);

        $this->assertArrayNotHasKey('is_wishlisted', $response->json('data'));
    }
}
