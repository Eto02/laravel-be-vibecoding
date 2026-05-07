<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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
}
