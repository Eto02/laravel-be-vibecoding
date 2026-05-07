<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\Store;
use App\Models\StoreFollower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    // ── GET /stores/{slug} ────────────────────────────────────────────────────

    public function test_public_can_view_store_profile(): void
    {
        $store = Store::factory()->create(['name' => 'Toko Hebat']);

        $this->getJson("/api/stores/{$store->slug}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'city', 'province', 'rating_avg', 'follower_count']]);
    }

    public function test_store_not_found_returns_404(): void
    {
        $this->getJson('/api/stores/slug-yang-tidak-ada')
            ->assertStatus(404);
    }

    // ── GET /stores/{slug}/products ───────────────────────────────────────────

    public function test_public_can_view_store_products(): void
    {
        $store = Store::factory()->create();

        $this->getJson("/api/stores/{$store->slug}/products")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    // ── GET /stores/{slug}/followers ──────────────────────────────────────────

    public function test_public_can_view_followers(): void
    {
        $store = Store::factory()->create();

        $this->getJson("/api/stores/{$store->slug}/followers")
            ->assertStatus(200);
    }

    // ── POST /stores/{slug}/follow ────────────────────────────────────────────

    public function test_follow_requires_authentication(): void
    {
        $store = Store::factory()->create();

        $this->postJson("/api/stores/{$store->slug}/follow")
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_follow_store(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/stores/{$store->slug}/follow")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('store_followers', ['store_id' => $store->id, 'user_id' => $user->id]);
    }

    public function test_user_cannot_follow_own_store(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson("/api/stores/{$store->slug}/follow")
            ->assertStatus(422);
    }

    public function test_user_cannot_follow_same_store_twice(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->create();

        StoreFollower::create(['store_id' => $store->id, 'user_id' => $user->id, 'created_at' => now()]);

        $this->actingAs($user)
            ->postJson("/api/stores/{$store->slug}/follow")
            ->assertStatus(409)
            ->assertJson(['success' => false]);
    }

    // ── DELETE /stores/{slug}/follow ──────────────────────────────────────────

    public function test_user_can_unfollow_store(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->create();
        StoreFollower::create(['store_id' => $store->id, 'user_id' => $user->id, 'created_at' => now()]);

        $this->actingAs($user)
            ->deleteJson("/api/stores/{$store->slug}/follow")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('store_followers', ['store_id' => $store->id, 'user_id' => $user->id]);
    }

    public function test_unfollow_requires_authentication(): void
    {
        $store = Store::factory()->create();

        $this->deleteJson("/api/stores/{$store->slug}/follow")
            ->assertStatus(401);
    }
}
