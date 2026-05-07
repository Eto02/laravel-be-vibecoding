<?php

namespace App\Services\Product;

use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\MediaServiceInterface;
use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\UpdateProductDTO;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private readonly MediaServiceInterface $media,
        private readonly CacheServiceInterface $cache,
        private readonly CategoryService $categories,
    ) {}

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function create(CreateProductDTO $data): Product
    {
        return Product::create([
            'store_id'    => $data->storeId,
            'category_id' => $data->categoryId,
            'name'        => $data->name,
            'slug'        => $this->generateUniqueSlug($data->name),
            'description' => $data->description,
            'status'      => ProductStatus::Draft,
            'weight_gram' => $data->weightGram,
        ]);
    }

    public function update(Product $product, UpdateProductDTO $data): Product
    {
        $product->update([
            'category_id' => $data->categoryId,
            'name'        => $data->name,
            'description' => $data->description,
            'weight_gram' => $data->weightGram,
        ]);

        $this->cache->forget("product:detail:{$product->slug}");
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $this->cache->forget("product:detail:{$product->slug}");
        $product->delete();
    }

    public function updateStatus(Product $product, ProductStatus $status): Product
    {
        if ($product->status === ProductStatus::Banned) {
            throw new \DomainException('Cannot change status of a banned product.');
        }

        if ($status === ProductStatus::Banned) {
            throw new AuthorizationException('Only admins can ban products.');
        }

        $product->update(['status' => $status]);
        $this->cache->forget("product:detail:{$product->slug}");
        return $product->fresh();
    }

    // ── Listings ──────────────────────────────────────────────────────────────

    public function getPublicListing(array $filters): LengthAwarePaginator
    {
        $query = Product::with(['store:id,name,slug', 'category:id,name,slug', 'media' => fn ($q) => $q->where('is_primary', true)])
            ->where('status', ProductStatus::Active);

        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%")
            );
        }

        if (! empty($filters['category'])) {
            $category = $this->categories->findBySlug($filters['category']);
            if ($category) {
                $ids = $this->categories->getDescendantIds($category);
                $query->whereIn('category_id', $ids);
            }
        }

        if (! empty($filters['min_price'])) {
            $query->where('min_price', '>=', (int) $filters['min_price'] * 100);
        }

        if (! empty($filters['max_price'])) {
            $query->where('max_price', '<=', (int) $filters['max_price'] * 100);
        }

        if (! empty($filters['store'])) {
            $query->whereHas('store', fn ($q) => $q->where('slug', $filters['store']));
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc'  => $query->orderBy('min_price'),
            'price_desc' => $query->orderByDesc('min_price'),
            'popular'    => $query->orderByDesc('sold_count'),
            default      => $query->latest(),
        };

        return $query->paginate(20);
    }

    public function getStoreProducts(Store $store, array $filters): LengthAwarePaginator
    {
        $page = $filters['page'] ?? 1;
        $cacheKey = "store:products:{$store->slug}:page:{$page}";

        return $this->cache->remember($cacheKey, 300, fn () =>
            Product::with(['media' => fn ($q) => $q->where('is_primary', true)])
                ->where('store_id', $store->id)
                ->where('status', ProductStatus::Active)
                ->latest()
                ->paginate(20)
        );
    }

    public function getMerchantProducts(int $storeId): LengthAwarePaginator
    {
        return Product::where('store_id', $storeId)->latest()->paginate(20);
    }

    public function getProductDetail(string $slug): Product
    {
        return $this->cache->remember("product:detail:{$slug}", 300, fn () =>
            Product::with(['store:id,name,slug', 'category:id,name,slug', 'variants', 'media'])
                ->where('slug', $slug)
                ->where('status', ProductStatus::Active)
                ->firstOrFail()
        );
    }

    // ── Media ─────────────────────────────────────────────────────────────────

    public function generateMediaPresignedUrl(Product $product, string $filename, string $mime): array
    {
        return $this->media->generatePresignedUrl("products/{$product->id}", $filename, $mime);
    }

    public function confirmMediaUpload(Product $product, string $key, string $type = 'image'): ProductMedia
    {
        $this->media->confirmUpload($key);

        $isPrimary = $product->media()->count() === 0;
        $sortOrder = $product->media()->max('sort_order') + 1;

        return ProductMedia::create([
            'product_id' => $product->id,
            'file'       => $key,
            'type'       => $type,
            'sort_order' => $isPrimary ? 0 : $sortOrder,
            'is_primary' => $isPrimary,
        ]);
    }

    public function deleteMedia(Product $product, ProductMedia $media): void
    {
        $this->media->delete($media->file);
        $wasPrimary = $media->is_primary;
        $media->delete();

        if ($wasPrimary) {
            $next = $product->media()->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }

        $this->cache->forget("product:detail:{$product->slug}");
    }

    public function reorderMedia(Product $product, array $items): void
    {
        $validIds = ProductMedia::where('product_id', $product->id)->pluck('id')->toArray();

        foreach ($items as $item) {
            if (! in_array($item['id'], $validIds)) {
                throw new AuthorizationException();
            }
        }

        foreach ($items as $item) {
            ProductMedia::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $this->cache->forget("product:detail:{$product->slug}");
    }

    // ── Variants ──────────────────────────────────────────────────────────────

    public function addVariant(Product $product, array $data): ProductVariant
    {
        return ProductVariant::create(array_merge($data, ['product_id' => $product->id]));
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update($data);
        return $variant->fresh();
    }

    public function deleteVariant(ProductVariant $variant): void
    {
        $variant->delete();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
