<?php

namespace Tests\Unit\Services\Cart;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Cart\CartService;
use App\DTOs\Cart\AddCartItemDTO;
use App\Contracts\Shared\CacheServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = $this->mock(CacheServiceInterface::class, function ($mock) {
            $mock->shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());
            $mock->shouldReceive('forget')->andReturn(true);
        });

        $this->service = new CartService($cache);
    }

    private function makeActiveVariant(int $stock = 10): ProductVariant
    {
        $store   = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Active]);
        return ProductVariant::factory()->for($product)->create(['stock' => $stock, 'price' => 100000]);
    }

    public function test_add_item_creates_cart_if_not_exists(): void
    {
        $user    = User::factory()->create();
        $variant = $this->makeActiveVariant();

        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);

        $this->service->add($user, new AddCartItemDTO($variant->id, 1));

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }

    public function test_add_item_increments_quantity_for_duplicate_variant(): void
    {
        $user    = User::factory()->create();
        $variant = $this->makeActiveVariant(20);

        $this->service->add($user, new AddCartItemDTO($variant->id, 2));
        $this->service->add($user, new AddCartItemDTO($variant->id, 3));

        $this->assertEquals(1, CartItem::where('product_variant_id', $variant->id)->count());
        $this->assertEquals(5, CartItem::where('product_variant_id', $variant->id)->value('quantity'));
    }

    public function test_group_by_store_returns_correct_structure(): void
    {
        $user     = User::factory()->create();
        $variant1 = $this->makeActiveVariant();
        $variant2 = $this->makeActiveVariant();

        $this->service->add($user, new AddCartItemDTO($variant1->id, 1));
        $this->service->add($user, new AddCartItemDTO($variant2->id, 1));

        $cart   = Cart::with('items.variant.product', 'items.store')->where('user_id', $user->id)->first();
        $groups = $this->service->groupByStore($cart);

        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            $this->assertArrayHasKey('store', $group);
            $this->assertArrayHasKey('items', $group);
            $this->assertNotEmpty($group['items']);
        }
    }

    public function test_group_by_store_excludes_unavailable_items(): void
    {
        $user    = User::factory()->create();
        $variant = $this->makeActiveVariant();

        $this->service->add($user, new AddCartItemDTO($variant->id, 1));

        $variant->product->delete();

        $cart   = Cart::with(['items.variant.product', 'items.store'])->where('user_id', $user->id)->first();
        $groups = $this->service->groupByStore($cart);

        foreach ($groups as $group) {
            foreach ($group['items'] as $itemData) {
                $this->assertFalse($itemData['is_available']);
            }
        }
    }
}
