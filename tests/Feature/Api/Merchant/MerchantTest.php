<?php

namespace Tests\Feature\Api\Merchant;

use App\Contracts\Shared\MediaServiceInterface;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Toko Bagus',
            'description' => 'Toko terbaik di kota',
            'city'        => 'Bandung',
            'province'    => 'Jawa Barat',
        ], $overrides);
    }

    // ── POST /merchant/register ────────────────────────────────────────────────

    public function test_register_store_requires_authentication(): void
    {
        $this->postJson('/api/merchant/register', $this->storePayload())
            ->assertStatus(401);
    }

    public function test_register_store_creates_store_with_pending_status(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->postJson('/api/merchant/register', $this->storePayload())
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'status', 'kyc_status']])
            ->assertJson(['data' => ['status' => 'pending', 'kyc_status' => 'pending']]);

        $this->assertDatabaseHas('stores', ['user_id' => $user->id, 'name' => 'Toko Bagus']);
    }

    public function test_register_store_generates_unique_slug(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)
            ->postJson('/api/merchant/register', $this->storePayload(['name' => 'My Store']));

        $response->assertStatus(201);
        $slug = $response->json('data.slug');
        $this->assertStringStartsWith('my-store', $slug);
    }

    public function test_register_store_fails_if_user_already_has_store(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson('/api/merchant/register', $this->storePayload())
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_register_store_validates_required_fields(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/merchant/register', [])
            ->assertStatus(422);
    }

    // ── GET /merchant/store ───────────────────────────────────────────────────

    public function test_merchant_can_view_own_store(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/api/merchant/store')
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => $store->id, 'name' => $store->name]]);
    }

    public function test_non_merchant_cannot_access_merchant_store(): void
    {
        $user = $this->actingUser(); // no store

        $this->actingAs($user)
            ->getJson('/api/merchant/store')
            ->assertStatus(403);
    }

    // ── PUT /merchant/store ───────────────────────────────────────────────────

    public function test_merchant_can_update_store(): void
    {
        $user  = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson('/api/merchant/store', ['name' => 'New Store Name', 'city' => 'Jakarta'])
            ->assertStatus(200)
            ->assertJson(['data' => ['name' => 'New Store Name', 'city' => 'Jakarta']]);
    }

    public function test_update_store_rejects_prohibited_fields(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson('/api/merchant/store', ['status' => 'active'])
            ->assertStatus(422);
    }

    // ── GET /merchant/dashboard ───────────────────────────────────────────────

    public function test_merchant_can_view_dashboard(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/api/merchant/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['store', 'follower_count', 'rating_avg', 'total_sales']]);
    }

    public function test_non_merchant_cannot_access_dashboard(): void
    {
        $this->actingAs($this->actingUser())
            ->getJson('/api/merchant/dashboard')
            ->assertStatus(403);
    }

    // ── POST /merchant/kyc ────────────────────────────────────────────────────

    public function test_kyc_upload_returns_presigned_url(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->andReturn([
                    'upload_url' => 'https://r2.example.com/upload',
                    'key'        => 'kyc/ktp/uuid.jpg',
                    'public_url' => 'https://pub.r2.dev/kyc/ktp/uuid.jpg',
                ]);
        });

        $this->actingAs($user)
            ->postJson('/api/merchant/kyc', ['type' => 'ktp', 'filename' => 'ktp.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['upload_url', 'key', 'public_url']]);
    }

    public function test_kyc_upload_fails_if_kyc_already_approved(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->kycApproved()->create();

        $this->actingAs($user)
            ->postJson('/api/merchant/kyc', ['type' => 'ktp', 'filename' => 'ktp.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_kyc_upload_fails_if_kyc_submitted(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->kycSubmitted()->create();

        $this->actingAs($user)
            ->postJson('/api/merchant/kyc', ['type' => 'ktp', 'filename' => 'ktp.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
