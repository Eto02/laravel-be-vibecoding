<?php

namespace Tests\Feature\Api\Product;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function merchantUser(): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create();
        return [$user, $store];
    }

    // ── Public listing ────────────────────────────────────────────────────────

    public function test_public_can_list_products(): void
    {
        Product::factory()->active()->count(3)->create();

        $this->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_public_can_view_product_detail(): void
    {
        $product = Product::factory()->active()->create();

        $this->getJson("/api/products/{$product->slug}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'status']]);
    }

    public function test_public_can_filter_products_by_category(): void
    {
        $category = Category::factory()->create();
        Product::factory()->active()->create(['category_id' => $category->id]);
        Product::factory()->active()->count(2)->create();

        $this->getJson("/api/products?category={$category->slug}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_draft_products_not_shown_in_public_listing(): void
    {
        Product::factory()->draft()->create();

        $this->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ── Merchant CRUD ─────────────────────────────────────────────────────────

    public function test_merchant_can_create_product(): void
    {
        [$user] = $this->merchantUser();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/merchant/products', [
                'category_id' => $category->id,
                'name'        => 'Sepatu Keren',
                'description' => 'Sepatu yang sangat keren dan nyaman dipakai.',
                'weight_gram' => 500,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'slug', 'status']]);
    }

    public function test_product_slug_is_auto_generated(): void
    {
        [$user] = $this->merchantUser();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/merchant/products', [
                'category_id' => $category->id,
                'name'        => 'Tas Cantik',
                'description' => 'Tas yang cantik dan elegan.',
            ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.slug'));
        $this->assertStringContainsString('tas', $response->json('data.slug'));
    }

    public function test_non_merchant_cannot_create_product(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/merchant/products', [
                'category_id' => $category->id,
                'name'        => 'Produk Gagal',
                'description' => 'Ini tidak boleh dibuat.',
            ])
            ->assertStatus(403);
    }

    public function test_merchant_cannot_edit_other_merchants_product(): void
    {
        [$user] = $this->merchantUser();
        $otherProduct = Product::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/merchant/products/{$otherProduct->slug}", [
                'category_id' => $category->id,
                'name'        => 'Diubah Paksa',
                'description' => 'Ini seharusnya gagal.',
            ])
            ->assertStatus(403);
    }

    // ── Status lifecycle ──────────────────────────────────────────────────────

    public function test_merchant_can_update_product_status_to_active(): void
    {
        [$user, $store] = $this->merchantUser();
        $product = Product::factory()->for($store)->draft()->create();

        $this->actingAs($user)
            ->putJson("/api/merchant/products/{$product->slug}/status", ['status' => 'active'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_merchant_cannot_set_banned_status(): void
    {
        [$user, $store] = $this->merchantUser();
        $product = Product::factory()->for($store)->active()->create();

        $this->actingAs($user)
            ->putJson("/api/merchant/products/{$product->slug}/status", ['status' => 'banned'])
            ->assertStatus(422);
    }

    // ── Variants ──────────────────────────────────────────────────────────────

    public function test_merchant_can_add_variant(): void
    {
        [$user, $store] = $this->merchantUser();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($user)
            ->postJson("/api/merchant/products/{$product->slug}/variants", [
                'sku'        => 'SKU-TEST-001',
                'price'      => 5000000,
                'stock'      => 10,
                'attributes' => ['warna' => 'Merah'],
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'sku', 'price_cents', 'price', 'stock']]);
    }

    public function test_merchant_can_update_variant(): void
    {
        [$user, $store] = $this->merchantUser();
        $product = Product::factory()->for($store)->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => 5000000, 'stock' => 10]);

        $this->actingAs($user)
            ->putJson("/api/merchant/products/{$product->slug}/variants/{$variant->id}", [
                'price' => 7500000,
                'stock' => 5,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.price_cents', 7500000);
    }

    public function test_total_stock_synced_after_variant_update(): void
    {
        [$user, $store] = $this->merchantUser();
        $product = Product::factory()->for($store)->create(['total_stock' => 0]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 20]);

        $product->refresh();
        $this->assertEquals(20, $product->total_stock);

        $this->actingAs($user)
            ->putJson("/api/merchant/products/{$product->slug}/variants/{$variant->id}", ['stock' => 50])
            ->assertStatus(200);

        $product->refresh();
        $this->assertEquals(50, $product->total_stock);
    }

    public function test_min_max_price_synced_after_variant_change(): void
    {
        [$user, $store] = $this->merchantUser();
        $product = Product::factory()->for($store)->create();
        ProductVariant::factory()->for($product)->create(['price' => 3000000, 'stock' => 5]);
        ProductVariant::factory()->for($product)->create(['price' => 8000000, 'stock' => 5]);

        $product->refresh();
        $this->assertEquals(3000000, $product->min_price);
        $this->assertEquals(8000000, $product->max_price);
    }

    // ── Store public products ─────────────────────────────────────────────────

    public function test_store_products_public_endpoint_returns_paginated_list(): void
    {
        $store = Store::factory()->create();
        Product::factory()->for($store)->active()->count(3)->create();

        $this->getJson("/api/stores/{$store->slug}/products")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }
}
