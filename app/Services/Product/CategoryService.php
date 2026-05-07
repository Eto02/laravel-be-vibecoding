<?php

namespace App\Services\Product;

use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        private readonly CacheServiceInterface $cache,
    ) {}

    public function getTree(): array
    {
        return $this->cache->remember('category:tree', 3600, function () {
            $all = Category::orderBy('sort_order')->get();
            return $this->buildTree($all, null);
        });
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->cache->remember("category:slug:{$slug}", 3600, function () use ($slug) {
            return Category::where('slug', $slug)->first();
        });
    }

    public function getDescendantIds(Category $category): array
    {
        $all = Category::all();
        $ids = [];
        $this->collectDescendants($all, $category->id, $ids);
        return $ids;
    }

    public function create(array $data): Category
    {
        $category = Category::create($data);
        $this->cache->forget('category:tree');
        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        $this->cache->forget('category:tree');
        $this->cache->forget("category:slug:{$category->slug}");
        return $category->fresh();
    }

    private function buildTree(Collection $all, ?int $parentId): array
    {
        return $all
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (Category $cat) => [
                'id'         => $cat->id,
                'name'       => $cat->name,
                'slug'       => $cat->slug,
                'icon'       => $cat->icon,
                'level'      => $cat->level,
                'sort_order' => $cat->sort_order,
                'children'   => $this->buildTree($all, $cat->id),
            ])
            ->all();
    }

    private function collectDescendants(Collection $all, int $parentId, array &$ids): void
    {
        $ids[] = $parentId;
        $children = $all->where('parent_id', $parentId);
        foreach ($children as $child) {
            $this->collectDescendants($all, $child->id, $ids);
        }
    }
}
