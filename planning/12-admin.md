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
- ⬜ Platform settings (komisi %, auto-approve merchant, batas produk per toko)
- ⬜ Voucher & Flash Sale management (platform-wide)

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

# Products
GET    /api/admin/products                     [auth:sanctum, admin]
PUT    /api/admin/products/{slug}/approve      [auth:sanctum, admin]
PUT    /api/admin/products/{slug}/ban          [auth:sanctum, admin]

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
app/Models/PlatformSetting.php
database/migrations/xxxx_create_platform_settings_table.php
database/seeders/AdminUserSeeder.php
routes/api/admin.php
tests/Feature/Api/Admin/AdminTest.php
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
- Admin role di-assign via `UserRole` enum: `$user->update(['role' => UserRole::Admin->value])`
- Platform settings disimpan di DB tabel `platform_settings` (key-value), di-cache di Redis
- Revenue dashboard: aggregate dari `transactions` yang berstatus `paid`
- GMV (Gross Merchandise Value): total nilai transaksi sebelum potongan komisi platform
- Dispute: admin bertindak sebagai mediator — bisa refund ke buyer atau release ke merchant
- KYC approval: ubah `store.status` dari `pending` ke `active` + kirim email notifikasi
