<?php

namespace Tests\Feature\Api\Product;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_get_category_tree(): void
    {
        Category::factory()->count(3)->create();

        $this->getJson('/api/categories')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_category_tree_is_nested(): void
    {
        $parent = Category::factory()->create(['level' => 1]);
        Category::factory()->child($parent)->create(['level' => 2]);

        $response = $this->getJson('/api/categories')->assertStatus(200);

        $tree = $response->json('data');
        $this->assertNotEmpty($tree);

        $parentInTree = collect($tree)->firstWhere('slug', $parent->slug);
        $this->assertNotNull($parentInTree);
        $this->assertArrayHasKey('children', $parentInTree);
    }

    public function test_category_not_found_returns_404(): void
    {
        $this->getJson('/api/categories/slug-tidak-ada')
            ->assertStatus(404);
    }

    public function test_public_can_get_single_category(): void
    {
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->slug}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'level', 'children']]);
    }
}
