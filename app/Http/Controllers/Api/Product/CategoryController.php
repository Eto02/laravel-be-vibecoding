<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreCategoryRequest;
use App\Http\Requests\Product\UpdateCategoryRequest;
use App\Http\Resources\Product\CategoryResource;
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

        return ApiResponse::success('Category retrieved.', new CategoryResource($category->load('children')));
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return ApiResponse::success('Category created.', new CategoryResource($category), 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $updated = $this->categories->update($category, $request->validated());

        return ApiResponse::success('Category updated.', new CategoryResource($updated));
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
}
