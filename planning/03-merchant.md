# MODULE 3 — Merchant / Store
**Priority:** 🟠 P1 | **Status:** ✅ Selesai | **Sprint:** 3

---

## Yang Perlu Dibangun (Sprint 3 Scope)
- ✅ Store Registration (nama toko, slug, deskripsi, logo, alamat toko)
- ✅ Store Profile publik (info toko, rating, jumlah produk)
- ✅ KYC Document Upload (KTP, NPWP, SIUP)
- ✅ Store Followers (ikuti / batal ikuti)
- ✅ Merchant Dashboard summary

### Ditunda (Deferred — butuh modul lain)
- ❌ **Store Analytics** — revenue, produk terlaris per periode (depends on Order module, P1)
- ❌ **Store Settings** — jam operasional, auto-reply, kebijakan toko (non-critical, deferred to later sprint)
- ❌ `store_operational_hours` table — deferred bersama Store Settings

---

## Entities

| Tabel | Kolom Utama |
|---|---|
| `stores` | `id`, `user_id`(unique), `name`, `slug`(unique), `description`, `logo`, `banner`, `status`(enum), `kyc_status`(enum), `city`, `province`, `rating_avg`(default 0), `total_sales`(default 0), `follower_count`(default 0), `timestamps` |
| `store_documents` | `id`, `store_id`, `type`(ktp/npwp/siup), `file`, `status`(pending/approved/rejected), `reviewed_at`, `timestamps` |
| `store_followers` | `id`, `store_id`, `user_id`, `created_at` (unique: store_id+user_id) |

> **Catatan Kolom:**
> - `logo` / `banner` / `file` — simpan **storage key** saja (e.g. `stores/uuid.jpg`), bukan full URL. URL dihitung di Resource via `MediaService::publicUrl()`.
> - `kyc_status` kolom terpisah dari `status`. `status` = lifecycle toko (pending → active → suspended). `kyc_status` = alur verifikasi dokumen.
> - `follower_count`, `rating_avg`, `total_sales` = denormalized counter. Default 0, di-update via queue job (bukan live query COUNT).
> - `unique('user_id')` pada `stores` — satu user hanya boleh punya satu toko.

---

## Enums

```php
// app/Enums/MerchantStatus.php
enum MerchantStatus: string {
    case Pending   = 'pending';    // baru register, belum approve admin
    case Active    = 'active';     // aktif berjualan
    case Suspended = 'suspended';  // suspended sementara
    case Banned    = 'banned';     // banned permanen
}

// app/Enums/KycStatus.php — NEW, kolom terpisah di stores
enum KycStatus: string {
    case Pending   = 'pending';    // belum ada dokumen
    case Submitted = 'submitted';  // dokumen sudah diupload, menunggu review
    case Approved  = 'approved';   // dokumen disetujui
    case Rejected  = 'rejected';   // dokumen ditolak, bisa re-upload
}
```

---

## Middleware: `EnsureMerchantOwnership`

> ⚠️ `[auth:merchant]` **bukan** guard Sanctum yang valid. Gunakan middleware custom.

```php
// app/Http/Middleware/EnsureMerchantOwnership.php
// Dipasang via Route::middleware(['auth:sanctum', 'merchant']) setelah di-register di bootstrap/app.php
// Logic: cek $request->user()->store()->exists() → abort(403) jika tidak ada toko
```

Register di `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['merchant' => \App\Http\Middleware\EnsureMerchantOwnership::class]);
})
```

---

## Routes

```
# Store registration & merchant-owned routes
POST   /api/merchant/register                    [auth:sanctum]
GET    /api/merchant/store                       [auth:sanctum, merchant]
PUT    /api/merchant/store                       [auth:sanctum, merchant]
GET    /api/merchant/dashboard                   [auth:sanctum, merchant]
POST   /api/merchant/kyc                         [auth:sanctum, merchant]

# Public store profile
GET    /api/stores/{slug}                        [public]
GET    /api/stores/{slug}/products               [public] ← deferred Sprint 4 (Product module), return 501 stub

# Follow/Unfollow (authenticated users)
POST   /api/stores/{slug}/follow                 [auth:sanctum]
DELETE /api/stores/{slug}/follow                 [auth:sanctum]
GET    /api/stores/{slug}/followers              [public]
```

> Route prefix `merchant` di-group di `routes/api/merchant.php`. Gunakan Route naming: `merchant.store.show`, `merchant.store.update`, `merchant.kyc.store`, `stores.show`, `stores.follow`, dsb.

---

## Files to Create

```
# DTOs
app/DTOs/Merchant/RegisterMerchantDTO.php

# Controllers
app/Http/Controllers/Api/Merchant/MerchantController.php   # register, dashboard, kyc
app/Http/Controllers/Api/Merchant/StoreController.php       # show, update (merchant-owned)
app/Http/Controllers/Api/Store/PublicStoreController.php    # public: show, followers (products deferred Sprint 4)
app/Http/Controllers/Api/Store/StoreFollowerController.php  # follow, unfollow

# Form Requests
app/Http/Requests/Merchant/RegisterMerchantRequest.php
app/Http/Requests/Merchant/UpdateStoreRequest.php
app/Http/Requests/Merchant/UploadKycRequest.php

# Resources
app/Http/Resources/Merchant/StoreResource.php               # full detail (merchant view)
app/Http/Resources/Merchant/StoreSummaryResource.php        # public card view
app/Http/Resources/Merchant/StoreDocumentResource.php

# Middleware
app/Http/Middleware/EnsureMerchantOwnership.php

# Services
app/Services/Merchant/MerchantService.php

# Models
app/Models/Store.php
app/Models/StoreDocument.php
app/Models/StoreFollower.php

# Enums
app/Enums/MerchantStatus.php
app/Enums/KycStatus.php

# Migrations
database/migrations/xxxx_create_stores_table.php            # unique: user_id, slug
database/migrations/xxxx_create_store_documents_table.php
database/migrations/xxxx_create_store_followers_table.php   # unique: store_id+user_id

# Factories
database/factories/StoreFactory.php
database/factories/StoreDocumentFactory.php
database/factories/StoreFollowerFactory.php

# Routes
routes/api/merchant.php

# Tests
tests/Feature/Api/Merchant/MerchantTest.php     # register, dashboard, kyc
tests/Feature/Api/Merchant/StoreTest.php        # public profile, followers
tests/Unit/Services/Merchant/MerchantServiceTest.php
```

---

## Shared Services Needed

| Service | Interface | Kegunaan |
|---|---|---|
| `MediaService` | `MediaServiceInterface` | Upload logo, banner, dokumen KYC (presigned URL flow) |
| `EmailService` | `EmailServiceInterface` | Notifikasi KYC approved/rejected via event |
| `CacheService` | `CacheServiceInterface` | Cache public store profile (TTL 600s) |

> `CacheService` dan `CacheServiceInterface` **sudah harus ada** dari Sprint sebelumnya. Jika belum, buat terlebih dahulu sebelum Sprint 3 dimulai.

---

## Business Logic Notes

### Slug Generation
- Auto-generate dari nama toko: `Str::slug($name)`
- Cek uniqueness via loop: tambahkan suffix `-2`, `-3`, dst jika slug sudah dipakai
- Implementasi di `MerchantService::generateUniqueSlug(string $name): string`
- Slug **tidak boleh diubah** setelah toko aktif (SEO & URL stability)

### Store Registration Flow
1. User POST `/api/merchant/register` → `MerchantService::register(User, RegisterMerchantDTO)`
2. Buat `Store` dengan `status = pending`, `kyc_status = pending`
3. Dispatch `MerchantRegistered` event (opsional, untuk welcome email)
4. Return `StoreResource`

### KYC Flow
- Upload hanya boleh jika `kyc_status IN (pending, rejected)`
- Presigned URL flow (sama seperti avatar): generate URL → client upload → confirm
- Setelah konfirmasi: buat/update `StoreDocument`, set `kyc_status = submitted`
- Admin approve/reject via Admin module (Sprint P2) — bukan bagian Sprint 3

### Follower Count
- `follow` → insert `store_followers` + dispatch `StoreFollowed` event
- `StoreFollowed` listener: `DB::table('stores')->increment('follower_count')`
- `unfollow` → delete `store_followers` + dispatch `StoreUnfollowed` event
- Listener harus `ShouldQueue` agar non-blocking

### Denormalized Counters (`rating_avg`, `total_sales`)
- Diisi 0 saat register, di-update via queue job dari modul Review / Order
- **Jangan** hitung live dengan `COUNT()` / `AVG()` pada setiap request publik

### Public Profile Caching
- `GET /api/stores/{slug}` → cache result via `CacheService` TTL 600s
- Key: `store:profile:{slug}`
- Invalidate cache saat store update (di `MerchantService::update()`)

### Media Upload
- Logo & banner: presigned URL flow (sama dengan avatar)
- Kolom DB simpan storage key, Resource compute URL:
```php
'logo_url'   => $this->logo   ? $this->media->publicUrl($this->logo)   : null,
'banner_url' => $this->banner ? $this->media->publicUrl($this->banner) : null,
```

### Authorization / IDOR Prevention
- `EnsureMerchantOwnership` middleware: cek `auth()->user()->store` exist
- `StorePolicy` (opsional): untuk cek ownership pada operasi spesifik
- Public routes tidak butuh auth

---

## Test Scenarios (Minimum)

### MerchantTest (Feature)
- `test_register_store_creates_store_with_pending_status`
- `test_register_store_generates_unique_slug`
- `test_register_store_fails_if_user_already_has_store`
- `test_merchant_can_view_own_store`
- `test_non_merchant_cannot_access_merchant_dashboard`
- `test_kyc_upload_returns_presigned_url`
- `test_kyc_upload_fails_if_kyc_already_approved`

### StoreTest (Feature)
- `test_public_can_view_store_profile`
- `test_store_not_found_returns_404`
- `test_authenticated_user_can_follow_store`
- `test_user_cannot_follow_own_store`
- `test_user_cannot_follow_same_store_twice`
- `test_user_can_unfollow_store`

### MerchantServiceTest (Unit)
- `test_generate_unique_slug_adds_suffix_on_collision`
- `test_register_creates_store_with_correct_defaults`

---

## DTOs

```php
// app/DTOs/Merchant/RegisterMerchantDTO.php
readonly class RegisterMerchantDTO {
    public function __construct(
        public string  $name,
        public string  $description,
        public string  $city,
        public string  $province,
        public ?string $phone = null,
    ) {}

    public static function fromRequest(RegisterMerchantRequest $request): self
    {
        return new self(
            name:        $request->input('name'),
            description: $request->input('description'),
            city:        $request->input('city'),
            province:    $request->input('province'),
            phone:       $request->input('phone'),
        );
    }
}
```

---

## Checklist Eksekusi

- [ ] Buat `EnsureMerchantOwnership` middleware dan register di bootstrap/app.php
- [ ] Buat `KycStatus` enum
- [ ] Buat `MerchantStatus` enum
- [ ] Buat migrations (stores, store_documents, store_followers) — stores dengan unique user_id
- [ ] Buat Models + Factories
- [ ] Buat `MerchantService` dengan `register()`, `update()`, `generateUniqueSlug()`, `uploadKyc()`
- [ ] Buat DTO `RegisterMerchantDTO`
- [ ] Buat Controllers (Merchant + Public Store + Follower)
- [ ] Buat FormRequests
- [ ] Buat Resources (StoreResource, StoreSummaryResource, StoreDocumentResource)
- [ ] Buat routes/api/merchant.php
- [ ] Buat Feature Tests dan Unit Tests
- [ ] Update Postman collection (folder "04. Merchant")
- [ ] Update planning/03-merchant.md → Status ✅ Selesai
