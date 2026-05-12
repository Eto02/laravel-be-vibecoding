# MODULE 12 — Admin Panel API
**Priority:** 🟡 P2 | **Status:** ⬜ Belum | **Sprint:** 12

---

## Yang Perlu Dibangun
- ⬜ User management (list, detail, ban, suspend, activate)
- ⬜ Merchant management (approve/reject toko, verifikasi KYC)
- ⬜ Product moderation (approve/reject/ban produk)
- ⬜ Review moderation (approve/reject review yang dilaporkan)
- ⬜ Dispute resolution (handle komplain buyer vs seller)
- ⬜ Platform revenue dashboard (komisi per transaksi, total GMV)
- ⬜ Platform settings (komisi %, active payment gateway, auto-approve merchant, batas produk per toko)
- ⬜ Voucher & Flash Sale management (platform-wide)
- ⬜ **`GatewayResolver`** — dynamic gateway switch via `platform_settings` + Redis cache (Tier 2)
- ⬜ **`CircuitBreakerGatewayResolver`** — auto-failover ke fallback gateway jika primary gagal N kali (Tier 3)

---

## Dependencies
- **Tidak pakai `spatie/laravel-permission`** — sudah diimplementasi via `UserRole` enum (Sprint 4)
- `users.role` kolom (string, default `'buyer'`) dengan cast `UserRole::class` di `User` model
- `EnsureAdminRole` middleware (alias `admin`) terdaftar di `bootstrap/app.php` — cek `$user->isAdmin()`
- Role assignment: `$user->update(['role' => UserRole::Admin->value])`
- Semua route admin wajib middleware: `['auth:sanctum', 'admin']`

---

## Routes (semua `[auth:sanctum, admin]`)
```
# Categories (sudah live sejak Sprint 4)
POST   /api/admin/categories                  [auth:sanctum, admin]  → StoreCategoryRequest
PUT    /api/admin/categories/{slug}            [auth:sanctum, admin]  → UpdateCategoryRequest
DELETE /api/admin/categories/{slug}            [auth:sanctum, admin]  → 422 if has children/products

# Users
GET    /api/admin/users                        [auth:sanctum, admin]
GET    /api/admin/users/{id}                   [auth:sanctum, admin]
PUT    /api/admin/users/{id}/ban               [auth:sanctum, admin]
PUT    /api/admin/users/{id}/activate          [auth:sanctum, admin]

# Merchants
GET    /api/admin/merchants                    [auth:sanctum, admin]
GET    /api/admin/merchants/{id}               [auth:sanctum, admin]
PUT    /api/admin/merchants/{id}/approve       [auth:sanctum, admin]
PUT    /api/admin/merchants/{id}/reject        [auth:sanctum, admin]
PUT    /api/admin/merchants/{id}/suspend       [auth:sanctum, admin]

# Products (binding via slug — Product::getRouteKeyName() = 'slug')
GET    /api/admin/products                     [auth:sanctum, admin]
PUT    /api/admin/products/{slug}/approve      [auth:sanctum, admin]  → set status = 'active'
PUT    /api/admin/products/{slug}/ban          [auth:sanctum, admin]  → set status = 'banned' (admin-only, merchant cannot revert)

# Reviews
GET    /api/admin/reviews?status=pending       [auth:sanctum, admin]
PUT    /api/admin/reviews/{id}/approve         [auth:sanctum, admin]
PUT    /api/admin/reviews/{id}/reject          [auth:sanctum, admin]

# Disputes
GET    /api/admin/disputes                     [auth:sanctum, admin]
GET    /api/admin/disputes/{id}                [auth:sanctum, admin]
PUT    /api/admin/disputes/{id}/resolve        [auth:sanctum, admin]

# Dashboard
GET    /api/admin/dashboard                    [auth:sanctum, admin]
GET    /api/admin/revenue                      [auth:sanctum, admin]
GET    /api/admin/revenue/export               [auth:sanctum, admin]

# Settings
GET    /api/admin/settings                     [auth:sanctum, admin]
PUT    /api/admin/settings                     [auth:sanctum, admin]
```

---

## Files to Create
```
app/Http/Controllers/Api/Admin/AdminUserController.php
app/Http/Controllers/Api/Admin/AdminMerchantController.php
app/Http/Controllers/Api/Admin/AdminProductController.php
app/Http/Controllers/Api/Admin/AdminReviewController.php
app/Http/Controllers/Api/Admin/AdminDisputeController.php
app/Http/Controllers/Api/Admin/AdminDashboardController.php
app/Http/Controllers/Api/Admin/AdminSettingController.php
app/Http/Resources/Admin/AdminUserResource.php
app/Http/Resources/Admin/AdminMerchantResource.php
app/Services/Admin/AdminUserService.php
app/Services/Admin/AdminMerchantService.php
app/Services/Admin/AdminDashboardService.php
app/Services/Payment/GatewayResolver.php                    (new — Tier 2)
app/Services/Payment/CircuitBreakerGatewayResolver.php      (new — Tier 3)
app/Models/PlatformSetting.php
database/migrations/xxxx_create_platform_settings_table.php
database/seeders/AdminUserSeeder.php
routes/api/admin.php
tests/Feature/Api/Admin/AdminTest.php
tests/Unit/Services/Payment/GatewayResolverTest.php         (new)
tests/Unit/Services/Payment/CircuitBreakerTest.php          (new)
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `EmailService` | Notifikasi KYC approved/rejected ke merchant |
| `CacheService` | Cache dashboard metrics (TTL 300s) |

> **Notifikasi:** Gunakan Event-driven approach (CLAUDE.md rule 11). Dispatch `Merchant\MerchantApproved` / `Merchant\MerchantRejected` events. Listener yang implements `ShouldQueue` menangani email — jangan inject `NotificationService` langsung ke Admin service.

---

## Business Logic Notes

### General
- Admin role di-assign via `UserRole` enum: `$user->update(['role' => UserRole::Admin->value])`
- Platform settings disimpan di DB tabel `platform_settings` (key-value), di-cache di Redis
- Revenue dashboard: aggregate dari `payments` yang berstatus `paid`
- GMV (Gross Merchandise Value): total nilai transaksi sebelum potongan komisi platform
- Dispute: admin bertindak sebagai mediator — bisa refund ke buyer atau release ke merchant
- KYC approval: ubah `store.status` dari `pending` ke `active` + set `store.kyc_status = 'approved'` + dispatch `Merchant\MerchantApproved` event (Listener kirim email)
- **Admin product ban:** `AdminProductController::ban()` memanggil `ProductService::adminSetStatus($product, ProductStatus::Banned)` — bypass validasi merchant.
- **Admin users table:** untuk ban user: set `email_verified_at = null` (blokir login) atau tambahkan `banned_at` kolom terpisah di Sprint 12.

### GatewayResolver (Tier 2 — dynamic switch)

```php
// app/Services/Payment/GatewayResolver.php
class GatewayResolver {
    public function resolve(): PaymentGatewayInterface {
        $gateway = Cache::remember('payment:active_gateway', 60, fn() =>
            PlatformSetting::getValue('payment_gateway', config('payment.default_gateway', 'xendit'))
        );
        return app("payment.{$gateway}");
    }

    public function switch(string $gateway): void {
        PlatformSetting::setValue('payment_gateway', $gateway);
        Cache::forget('payment:active_gateway');
    }
}
```

- Admin switch gateway via `PUT /api/admin/settings` dengan body `{ "payment_gateway": "midtrans" }`
- Efektif dalam 60 detik (Redis TTL cache)
- `AppServiceProvider` binding diupdate: `PaymentGatewayInterface` → resolve via `GatewayResolver`

### CircuitBreakerGatewayResolver (Tier 3 — auto-failover)

```php
// app/Services/Payment/CircuitBreakerGatewayResolver.php
// Wraps GatewayResolver, tambah Redis-backed failure tracking
```

| Parameter | Default | Keterangan |
|---|---|---|
| `THRESHOLD` | 5 | Jumlah failure sebelum circuit "open" |
| `WINDOW` | 60s | Window waktu penghitungan failure |
| `RECOVERY` | 30s | Jeda sebelum coba primary lagi ("half-open") |

- `PaymentService` panggil `resolver->recordSuccess($gateway)` / `recordFailure($gateway)` setelah setiap gateway call
- Jika circuit "open" → auto-route ke fallback tanpa intervensi manual
- Setelah `RECOVERY` seconds → coba primary lagi ("half-open"); jika sukses → close circuit

### Migration dari Sprint 7 ke Sprint 12

Hanya 2 perubahan saat Sprint 12 aktif:
1. `AppServiceProvider::bind(PaymentGatewayInterface::class)` → delegate ke `CircuitBreakerGatewayResolver`
2. `WalletService::creditMerchant()` → ganti `config('platform.fee_percent')` ke `PlatformSetting::getValue('fee_percent')`

`PaymentService`, `XenditPaymentService`, `MidtransPaymentService` **tidak perlu diubah sama sekali**.
- **Files to Create** perlu ditambahkan: `app/Http/Requests/Admin/AdminUpdateProductStatusRequest.php` (izinkan `banned`)
