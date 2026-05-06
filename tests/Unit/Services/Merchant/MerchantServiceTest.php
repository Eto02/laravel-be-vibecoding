<?php

namespace Tests\Unit\Services\Merchant;

use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\MediaServiceInterface;
use App\DTOs\Merchant\RegisterMerchantDTO;
use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Exceptions\Merchant\AlreadyFollowingException;
use App\Exceptions\Merchant\KycNotAllowedException;
use App\Exceptions\Merchant\StoreAlreadyExistsException;
use App\Models\Store;
use App\Models\StoreFollower;
use App\Models\User;
use App\Services\Merchant\MerchantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantServiceTest extends TestCase
{
    use RefreshDatabase;

    private MerchantService $service;
    private MediaServiceInterface $media;
    private CacheServiceInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->media   = $this->createMock(MediaServiceInterface::class);
        $this->cache   = $this->createMock(CacheServiceInterface::class);
        $this->service = new MerchantService($this->media, $this->cache);
    }

    private function dto(array $overrides = []): RegisterMerchantDTO
    {
        return new RegisterMerchantDTO(
            name:        $overrides['name']        ?? 'Toko Test',
            description: $overrides['description'] ?? 'Deskripsi toko',
            city:        $overrides['city']        ?? 'Bandung',
            province:    $overrides['province']    ?? 'Jawa Barat',
        );
    }

    public function test_register_creates_store_with_correct_defaults(): void
    {
        $user  = User::factory()->create();
        $store = $this->service->register($user, $this->dto());

        $this->assertSame($user->id, $store->user_id);
        $this->assertSame(MerchantStatus::Pending, $store->status);
        $this->assertSame(KycStatus::Pending, $store->kyc_status);
        $this->assertSame(0, (int) $store->follower_count);
    }

    public function test_register_throws_if_user_already_has_store(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->expectException(StoreAlreadyExistsException::class);
        $this->service->register($user, $this->dto());
    }

    public function test_generate_unique_slug_adds_suffix_on_collision(): void
    {
        Store::factory()->create(['slug' => 'toko-test']);
        Store::factory()->create(['slug' => 'toko-test-2']);

        $slug = $this->service->generateUniqueSlug('Toko Test');

        $this->assertSame('toko-test-3', $slug);
    }

    public function test_generate_unique_slug_returns_base_when_no_collision(): void
    {
        $slug = $this->service->generateUniqueSlug('Brand New Store');

        $this->assertSame('brand-new-store', $slug);
    }

    public function test_kyc_upload_throws_when_status_is_approved(): void
    {
        $store = Store::factory()->kycApproved()->create();

        $this->expectException(KycNotAllowedException::class);
        $this->service->generateKycPresignedUrl($store, 'ktp', 'ktp.jpg', 'image/jpeg');
    }

    public function test_kyc_upload_throws_when_status_is_submitted(): void
    {
        $store = Store::factory()->kycSubmitted()->create();

        $this->expectException(KycNotAllowedException::class);
        $this->service->generateKycPresignedUrl($store, 'ktp', 'ktp.jpg', 'image/jpeg');
    }

    public function test_follow_throws_when_already_following(): void
    {
        $user  = User::factory()->create();
        $store = Store::factory()->create();

        StoreFollower::create(['store_id' => $store->id, 'user_id' => $user->id, 'created_at' => now()]);

        $this->expectException(AlreadyFollowingException::class);
        $this->service->follow($user, $store);
    }
}
