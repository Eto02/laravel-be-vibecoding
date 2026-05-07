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

    public function test_register_store_sets_user_role_to_merchant(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->postJson('/api/merchant/register', $this->storePayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'role' => 'merchant',
        ]);
    }

    public function test_suspended_store_cannot_access_merchant_endpoints(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->suspended()->create();

        $this->actingAs($user)
            ->getJson('/api/merchant/store')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your store has been suspended.');
    }

    public function test_banned_store_cannot_access_merchant_endpoints(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->banned()->create();

        $this->actingAs($user)
            ->getJson('/api/merchant/store')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your store has been suspended.');
    }

    public function test_pending_store_can_still_access_merchant_endpoints(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->pending()->create();

        $this->actingAs($user)
            ->getJson('/api/merchant/store')
            ->assertStatus(200);
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

    // ── PUT /merchant/kyc (re-upload, rejected only) ──────────────────────────

    public function test_kyc_reupload_succeeds_when_rejected(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create(['kyc_status' => 'rejected']);

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->andReturn([
                    'upload_url' => 'https://r2.example.com/upload',
                    'key'        => 'kyc/ktp/uuid2.jpg',
                    'public_url' => 'https://pub.r2.dev/kyc/ktp/uuid2.jpg',
                ]);
        });

        $this->actingAs($user)
            ->putJson('/api/merchant/kyc', ['type' => 'ktp', 'filename' => 'ktp-baru.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['upload_url', 'key', 'public_url']]);
    }

    public function test_kyc_reupload_fails_when_pending(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create(['kyc_status' => 'pending']);

        $this->actingAs($user)
            ->putJson('/api/merchant/kyc', ['type' => 'ktp', 'filename' => 'ktp.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_kyc_reupload_fails_when_approved(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->kycApproved()->create();

        $this->actingAs($user)
            ->putJson('/api/merchant/kyc', ['type' => 'ktp', 'filename' => 'ktp.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ── POST /merchant/store/logo ──────────────────────────────────────────────

    public function test_upload_logo_returns_presigned_url(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->andReturn([
                    'upload_url' => 'https://r2.example.com/upload',
                    'key'        => 'stores/1/logo/uuid.jpg',
                    'public_url' => 'https://pub.r2.dev/stores/1/logo/uuid.jpg',
                ]);
        });

        $this->actingAs($user)
            ->postJson('/api/merchant/store/logo', ['filename' => 'logo.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['upload_url', 'key', 'public_url']]);
    }

    public function test_confirm_logo_saves_key_and_deletes_old(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create(['logo' => 'stores/1/logo/old.jpg']);

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('confirmUpload')->once()->with('stores/1/logo/new.jpg')->andReturn(true);
            $mock->shouldReceive('delete')->once()->with('stores/1/logo/old.jpg');
        });

        $this->actingAs($user)
            ->postJson('/api/merchant/store/logo/confirm', ['key' => 'stores/1/logo/new.jpg'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'logo' => 'stores/1/logo/new.jpg']);
    }

    public function test_delete_logo_returns_204(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create(['logo' => 'stores/1/logo/uuid.jpg']);

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->with('stores/1/logo/uuid.jpg');
        });

        $this->actingAs($user)
            ->deleteJson('/api/merchant/store/logo')
            ->assertStatus(204);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'logo' => null]);
    }

    // ── POST /merchant/store/banner ────────────────────────────────────────────

    public function test_upload_banner_returns_presigned_url(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->andReturn([
                    'upload_url' => 'https://r2.example.com/upload',
                    'key'        => 'stores/1/banner/uuid.jpg',
                    'public_url' => 'https://pub.r2.dev/stores/1/banner/uuid.jpg',
                ]);
        });

        $this->actingAs($user)
            ->postJson('/api/merchant/store/banner', ['filename' => 'banner.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['upload_url', 'key', 'public_url']]);
    }

    public function test_confirm_banner_saves_key_and_deletes_old(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create(['banner' => 'stores/1/banner/old.jpg']);

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('confirmUpload')->once()->with('stores/1/banner/new.jpg')->andReturn(true);
            $mock->shouldReceive('delete')->once()->with('stores/1/banner/old.jpg');
        });

        $this->actingAs($user)
            ->postJson('/api/merchant/store/banner/confirm', ['key' => 'stores/1/banner/new.jpg'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'banner' => 'stores/1/banner/new.jpg']);
    }

    public function test_delete_banner_returns_204(): void
    {
        $user  = $this->actingUser();
        $store = Store::factory()->for($user)->create(['banner' => 'stores/1/banner/uuid.jpg']);

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->with('stores/1/banner/uuid.jpg');
        });

        $this->actingAs($user)
            ->deleteJson('/api/merchant/store/banner')
            ->assertStatus(204);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'banner' => null]);
    }

    public function test_upload_logo_rejects_non_image_mime(): void
    {
        $user = $this->actingUser();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson('/api/merchant/store/logo', ['filename' => 'doc.pdf', 'mime' => 'application/pdf'])
            ->assertStatus(422);
    }
}
