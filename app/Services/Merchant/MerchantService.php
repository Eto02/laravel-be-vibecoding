<?php

namespace App\Services\Merchant;

use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\MediaServiceInterface;
use App\DTOs\Merchant\RegisterMerchantDTO;
use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Enums\UserRole;
use App\Events\Merchant\StoreFollowed;
use App\Events\Merchant\StoreUnfollowed;
use App\Exceptions\Merchant\AlreadyFollowingException;
use App\Exceptions\Merchant\KycNotAllowedException;
use App\Exceptions\Merchant\StoreAlreadyExistsException;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\StoreFollower;
use App\Models\User;
use Illuminate\Support\Str;

class MerchantService
{
    public function __construct(
        private readonly MediaServiceInterface $media,
        private readonly CacheServiceInterface $cache,
    ) {}

    public function register(User $user, RegisterMerchantDTO $data): Store
    {
        if ($user->store()->exists()) {
            throw new StoreAlreadyExistsException();
        }

        $store = Store::create([
            'user_id'     => $user->id,
            'name'        => $data->name,
            'slug'        => $this->generateUniqueSlug($data->name),
            'description' => $data->description,
            'city'        => $data->city,
            'province'    => $data->province,
            'phone'       => $data->phone,
            'status'      => MerchantStatus::Pending,
            'kyc_status'  => KycStatus::Pending,
        ]);

        $user->update(['role' => UserRole::Merchant->value]);

        return $store;
    }

    public function update(Store $store, array $data): Store
    {
        $store->update($data);
        $this->cache->forget("store:profile:{$store->slug}");

        return $store->fresh();
    }

    public function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 2;

        while (Store::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function generateKycPresignedUrl(Store $store, string $type, string $filename, string $mime): array
    {
        if (! in_array($store->kyc_status, [KycStatus::Pending, KycStatus::Rejected])) {
            throw new KycNotAllowedException();
        }

        return $this->media->generatePresignedUrl("kyc/{$type}", $filename, $mime);
    }

    public function generateKycReuploadUrl(Store $store, string $type, string $filename, string $mime): array
    {
        if ($store->kyc_status !== KycStatus::Rejected) {
            throw new KycNotAllowedException();
        }

        return $this->media->generatePresignedUrl("kyc/{$type}", $filename, $mime);
    }

    public function confirmKycUpload(Store $store, string $type, string $key): StoreDocument
    {
        if (! in_array($store->kyc_status, [KycStatus::Pending, KycStatus::Rejected])) {
            throw new KycNotAllowedException();
        }

        $this->media->confirmUpload($key);

        $existing = StoreDocument::where('store_id', $store->id)->where('type', $type)->first();
        if ($existing && $existing->file !== $key) {
            $this->media->delete($existing->file);
        }

        $document = StoreDocument::updateOrCreate(
            ['store_id' => $store->id, 'type' => $type],
            ['file' => $key, 'status' => 'pending', 'reviewed_at' => null],
        );

        $store->update(['kyc_status' => KycStatus::Submitted]);

        return $document;
    }

    public function generateLogoPresignedUrl(Store $store, string $filename, string $mime): array
    {
        return $this->media->generatePresignedUrl("stores/{$store->id}/logo", $filename, $mime);
    }

    public function confirmLogoUpload(Store $store, string $key): Store
    {
        if (! $this->media->confirmUpload($key)) {
            throw new \RuntimeException('Logo file not found in storage.');
        }

        if ($store->logo && $store->logo !== $key) {
            $this->media->delete($store->logo);
        }

        $store->update(['logo' => $key]);
        $this->cache->forget("store:profile:{$store->slug}");

        return $store->fresh();
    }

    public function deleteLogo(Store $store): void
    {
        if ($store->logo) {
            $this->media->delete($store->logo);
            $store->update(['logo' => null]);
            $this->cache->forget("store:profile:{$store->slug}");
        }
    }

    public function generateBannerPresignedUrl(Store $store, string $filename, string $mime): array
    {
        return $this->media->generatePresignedUrl("stores/{$store->id}/banner", $filename, $mime);
    }

    public function confirmBannerUpload(Store $store, string $key): Store
    {
        if (! $this->media->confirmUpload($key)) {
            throw new \RuntimeException('Banner file not found in storage.');
        }

        if ($store->banner && $store->banner !== $key) {
            $this->media->delete($store->banner);
        }

        $store->update(['banner' => $key]);
        $this->cache->forget("store:profile:{$store->slug}");

        return $store->fresh();
    }

    public function deleteBanner(Store $store): void
    {
        if ($store->banner) {
            $this->media->delete($store->banner);
            $store->update(['banner' => null]);
            $this->cache->forget("store:profile:{$store->slug}");
        }
    }

    public function follow(User $user, Store $store): void
    {
        if ($store->user_id === $user->id) {
            abort(422, 'You cannot follow your own store.');
        }

        if (StoreFollower::where('store_id', $store->id)->where('user_id', $user->id)->exists()) {
            throw new AlreadyFollowingException();
        }

        StoreFollower::create([
            'store_id'   => $store->id,
            'user_id'    => $user->id,
            'created_at' => now(),
        ]);

        StoreFollowed::dispatch($store);
    }

    public function unfollow(User $user, Store $store): void
    {
        StoreFollower::where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->delete();

        StoreUnfollowed::dispatch($store);
    }

    public function getPublicProfile(string $slug): Store
    {
        return $this->cache->remember(
            "store:profile:{$slug}",
            600,
            fn () => Store::where('slug', $slug)->firstOrFail(),
        );
    }

    public function getDashboard(Store $store): array
    {
        return [
            'store'          => $store,
            'follower_count' => $store->follower_count,
            'rating_avg'     => $store->rating_avg,
            'total_sales'    => $store->total_sales,
        ];
    }
}
