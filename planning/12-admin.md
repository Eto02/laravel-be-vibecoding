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
- `spatie/laravel-permission` — role: `buyer`, `merchant`, `admin`
- Role assignment via seeder atau command
- Semua route admin wajib middleware: `auth:sanctum` + `role:admin`

---

## Routes (semua `[auth:admin]`)
```
# Users
GET    /api/admin/users
GET    /api/admin/users/{id}
PUT    /api/admin/users/{id}/ban
PUT    /api/admin/users/{id}/activate

# Merchants
GET    /api/admin/merchants
GET    /api/admin/merchants/{id}
PUT    /api/admin/merchants/{id}/approve
PUT    /api/admin/merchants/{id}/reject
PUT    /api/admin/merchants/{id}/suspend

# Products
GET    /api/admin/products
PUT    /api/admin/products/{id}/approve
PUT    /api/admin/products/{id}/ban

# Reviews
GET    /api/admin/reviews?status=pending
PUT    /api/admin/reviews/{id}/approve
PUT    /api/admin/reviews/{id}/reject

# Disputes
GET    /api/admin/disputes
GET    /api/admin/disputes/{id}
PUT    /api/admin/disputes/{id}/resolve

# Dashboard
GET    /api/admin/dashboard
GET    /api/admin/revenue
GET    /api/admin/revenue/export

# Settings
GET    /api/admin/settings
PUT    /api/admin/settings
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
| `NotificationService` | Notif user/merchant terkait tindakan admin |
| `CacheService` | Cache dashboard metrics (TTL 300s) |

---

## Business Logic Notes
- Admin role di-assign via `spatie/laravel-permission`: `$user->assignRole('admin')`
- Platform settings disimpan di DB tabel `platform_settings` (key-value), di-cache di Redis
- Revenue dashboard: aggregate dari `transactions` yang berstatus `paid`
- GMV (Gross Merchandise Value): total nilai transaksi sebelum potongan komisi platform
- Dispute: admin bertindak sebagai mediator — bisa refund ke buyer atau release ke merchant
- KYC approval: ubah `store.status` dari `pending` ke `active` + kirim email notifikasi
