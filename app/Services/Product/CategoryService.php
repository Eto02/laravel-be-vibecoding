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
        if (isset($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            $data['level'] = ($parent?->level ?? 0) + 1;
        } else {
            $data['level'] = 1;
        }
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category = Category::create($data);
        $this->cache->forget('category:tree');
        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        if (isset($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            $data['level'] = ($parent?->level ?? 0) + 1;
        }

        $oldSlug = $category->slug;
        $category->update($data);
        $this->cache->forget('category:tree');
        $this->cache->forget("category:slug:{$oldSlug}");
        if (isset($data['slug']) && $data['slug'] !== $oldSlug) {
            $this->cache->forget("category:slug:{$data['slug']}");
        }
        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        if ($category->children()->count() > 0) {
            throw new \DomainException('Cannot delete a category that has children.');
        }

        if ($category->products()->count() > 0) {
            throw new \DomainException('Cannot delete a category that has products.');
        }

        $this->cache->forget('category:tree');
        $this->cache->forget("category:slug:{$category->slug}");
        $category->delete();
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
