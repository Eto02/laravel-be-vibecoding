<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreCategoryRequest;
use App\Http\Requests\Product\UpdateCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Services\Product\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    public function index(): JsonResponse
    {
        $tree = $this->categories->getTree();

        return ApiResponse::success('Categories retrieved.', $tree);
    }

    public function show(string $slug): JsonResponse
    {
        $category = $this->categories->findBySlug($slug);

        if (! $category) {
            return ApiResponse::error('Category not found.', 404);
        }

        $category->load('children');

        return ApiResponse::success('Category retrieved.', [
            'id'         => $category->id,
            'name'       => $category->name,
            'slug'       => $category->slug,
            'icon'       => $category->icon,
            'level'      => $category->level,
            'sort_order' => $category->sort_order,
            'children'   => $category->children->map(fn ($child) => [
                'id'    => $child->id,
                'name'  => $child->name,
                'slug'  => $child->slug,
                'level' => $child->level,
            ])->values()->all(),
        ]);
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            $data['level'] = $parent->level + 1;
        } else {
            $data['level'] = 1;
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category = $this->categories->create($data);

        return ApiResponse::success('Category created.', $this->formatCategory($category), 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            $data['level'] = $parent->level + 1;
        }

        $updated = $this->categories->update($category, $data);

        return ApiResponse::success('Category updated.', $this->formatCategory($updated));
    }

    public function destroy(Category $category): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        if ($category->children()->count() > 0) {
            return ApiResponse::error('Cannot delete a category that has children.', 422);
        }

        if ($category->products()->count() > 0) {
            return ApiResponse::error('Cannot delete a category that has products.', 422);
        }

        $this->categories->delete($category);

        return response()->noContent();
    }

    private function formatCategory(Category $category): array
    {
        return [
            'id'         => $category->id,
            'name'       => $category->name,
            'slug'       => $category->slug,
            'icon'       => $category->icon,
            'level'      => $category->level,
            'sort_order' => $category->sort_order,
            'parent_id'  => $category->parent_id,
        ];
    }
}
