<?php

namespace Tests\Feature\Api\Product;

use App\Models\Category;
use App\Models\User;
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

    // ── Admin CRUD ────────────────────────────────────────────────────────────

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/admin/categories', [
                'name'       => 'Elektronik',
                'slug'       => 'elektronik',
                'sort_order' => 0,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'level']])
            ->assertJsonPath('data.level', 1);

        $this->assertDatabaseHas('categories', ['slug' => 'elektronik']);
    }

    public function test_admin_can_create_child_category(): void
    {
        $admin  = User::factory()->admin()->create();
        $parent = Category::factory()->create(['level' => 1]);

        $this->actingAs($admin)
            ->postJson('/api/admin/categories', [
                'name'      => 'Handphone',
                'slug'      => 'handphone',
                'parent_id' => $parent->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.level', 2)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/admin/categories', [
                'name' => 'Coba',
                'slug' => 'coba',
            ])
            ->assertStatus(403);
    }

    public function test_guest_cannot_create_category(): void
    {
        $this->postJson('/api/admin/categories', [
            'name' => 'Coba',
            'slug' => 'coba',
        ])->assertStatus(401);
    }

    public function test_admin_can_update_category(): void
    {
        $admin    = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Lama', 'slug' => 'lama']);

        $this->actingAs($admin)
            ->putJson("/api/admin/categories/{$category->slug}", ['name' => 'Baru'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Baru');
    }

    public function test_admin_can_delete_empty_category(): void
    {
        $admin    = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/categories/{$category->slug}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_delete_category_with_children(): void
    {
        $admin  = User::factory()->admin()->create();
        $parent = Category::factory()->create(['level' => 1]);
        Category::factory()->child($parent)->create();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/categories/{$parent->slug}")
            ->assertStatus(422);
    }

    public function test_duplicate_slug_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['slug' => 'duplikat']);

        $this->actingAs($admin)
            ->postJson('/api/admin/categories', [
                'name' => 'Duplikat Baru',
                'slug' => 'duplikat',
            ])
            ->assertStatus(422);
    }
}
