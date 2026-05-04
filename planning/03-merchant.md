# MODULE 3 — Merchant / Store
**Priority:** 🟠 P1 | **Status:** ⬜ Belum | **Sprint:** 3

---

## Yang Perlu Dibangun
- ⬜ Store Registration (nama toko, slug, deskripsi, logo, alamat toko)
- ⬜ Store Profile publik (info toko, rating, jumlah produk)
- ⬜ Store Settings (jam operasional, auto-reply, kebijakan toko)
- ⬜ KYC Document Upload (KTP, NPWP)
- ⬜ Store Analytics (revenue, produk terlaris per periode)
- ⬜ Store Followers (ikuti / batal ikuti)
- ⬜ Merchant Dashboard summary

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `stores` | `user_id`, `name`, `slug`, `description`, `logo_url`, `banner_url`, `status` (enum), `city`, `province`, `rating_avg`, `total_sales`, `follower_count` |
| `store_documents` | `store_id`, `type` (ktp/npwp/siup), `file_url`, `status` (pending/approved/rejected) |
| `store_followers` | `store_id`, `user_id` |
| `store_operational_hours` | `store_id`, `day_of_week`, `open_time`, `close_time`, `is_closed` |

---

## Enums
```php
enum MerchantStatus: string {
    case Pending   = 'pending';
    case Active    = 'active';
    case Suspended = 'suspended';
    case Banned    = 'banned';
}
```

---

## Routes
```
POST /api/merchant/register                    [auth]
GET  /api/merchant/store                       [auth:merchant]
PUT  /api/merchant/store                       [auth:merchant]
GET  /api/merchant/dashboard                   [auth:merchant]
GET  /api/merchant/analytics                   [auth:merchant]
POST /api/merchant/kyc                         [auth:merchant]
GET  /api/stores/{slug}                        [public]
GET  /api/stores/{slug}/products               [public]
POST /api/stores/{slug}/follow                 [auth]
DELETE /api/stores/{slug}/follow               [auth]
GET  /api/stores/{slug}/followers              [public]
```

---

## Files to Create
```
app/Http/Controllers/Api/Merchant/MerchantController.php
app/Http/Controllers/Api/Merchant/StoreController.php
app/Http/Requests/Merchant/RegisterMerchantRequest.php
app/Http/Requests/Merchant/UpdateStoreRequest.php
app/Http/Requests/Merchant/UploadKycRequest.php
app/Http/Resources/Merchant/StoreResource.php
app/Http/Resources/Merchant/StoreSummaryResource.php
app/Services/Merchant/MerchantService.php
app/Models/Store.php
app/Models/StoreDocument.php
app/Models/StoreFollower.php
app/Enums/MerchantStatus.php
database/migrations/xxxx_create_stores_table.php
database/migrations/xxxx_create_store_documents_table.php
database/migrations/xxxx_create_store_followers_table.php
routes/api/merchant.php
tests/Feature/Api/Merchant/MerchantTest.php
tests/Feature/Api/Merchant/StoreTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `MediaService` | Upload logo, banner, dokumen KYC |
| `EmailService` | Notifikasi KYC approved/rejected |
| `CacheService` | Cache public store profile (TTL 600s) |

---

## Business Logic Notes
- Satu user hanya bisa punya satu toko
- Slug auto-generate dari nama toko (unique, URL-safe)
- Status awal toko: `pending` (perlu approve admin) atau `active` (tergantung konfigurasi)
- KYC: hanya bisa upload saat status `pending` atau `kyc_rejected`
- Analytics dihitung dari data `orders` dan `order_items`
- Follower count di-cache, di-update via queue job saat follow/unfollow
