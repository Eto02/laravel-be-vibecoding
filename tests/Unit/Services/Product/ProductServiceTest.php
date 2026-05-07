<?php

namespace Tests\Unit\Services\Product;

use App\Enums\ProductStatus;
use App\Services\Product\ProductService;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    public function test_generate_unique_slug_is_deterministic(): void
    {
        $service = $this->makeMockService();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateUniqueSlug');

        // Can't test DB uniqueness in unit tests without DB — just test Str::slug works
        $this->markTestSkipped('generateUniqueSlug requires DB — tested via Feature tests.');
    }

    public function test_cannot_transition_to_banned_status_raises_exception(): void
    {
        $service = $this->makeMockService();

        $product = new \App\Models\Product();
        $product->status = ProductStatus::Active;

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service->updateStatus($product, ProductStatus::Banned);
    }

    public function test_cannot_change_status_of_banned_product(): void
    {
        $service = $this->makeMockService();

        $product = new \App\Models\Product();
        $product->status = ProductStatus::Banned;

        $this->expectException(\DomainException::class);

        $service->updateStatus($product, ProductStatus::Active);
    }

    private function makeMockService(): ProductService
    {
        $media = $this->createMock(\App\Contracts\Shared\MediaServiceInterface::class);
        $cache = new class implements \App\Contracts\Shared\CacheServiceInterface {
            public function remember(string $key, int $ttl, callable $callback): mixed { return $callback(); }
            public function forget(string $key): void {}
            public function has(string $key): bool { return false; }
        };
        $categories = $this->createMock(\App\Services\Product\CategoryService::class);

        return new ProductService($media, $cache, $categories);
    }
}
