# Access Control — Role & Permission Reference
**Priority:** 🔴 P0 | **Status:** ✅ Implemented (Sprint 4)

> Dokumen ini adalah **single source of truth** untuk seluruh sistem role dan akses di project ini.
> Semua modul yang menyentuh auth/permission harus merujuk ke sini.

---

## Role System

Role disimpan di kolom `users.role` (string, default `'buyer'`), di-cast ke `UserRole` enum.

```php
// app/Enums/UserRole.php
enum UserRole: string {
    case Buyer    = 'buyer';    // semua user baru — default
    case Merchant = 'merchant'; // di-set saat store didaftarkan
    case Admin    = 'admin';    // di-set manual via seeder/tinker
}
```

### Kapan role di-set?

| Event | Role Column | Trigger |
|---|---|---|
| User register | `buyer` | Default dari DB |
| Merchant register store | `merchant` | `MerchantService::register()` — `$user->update(['role' => UserRole::Merchant])` |
| Admin assignment | `admin` | Manual via seeder / Tinker / Admin panel (Sprint 12) |
| Store suspended/banned | **tidak berubah** | `store.status` yang berubah, bukan `users.role` |

---

## Dua Layer Akses Merchant

Merchant diproteksi oleh **dua gate independen**:

```
Gate 1 — Identity:    users.role = 'merchant'   (di-set saat register)
Gate 2 — Operational: store.status != suspended/banned
```

Keduanya di-enforce oleh `EnsureMerchantOwnership` middleware:

```php
// app/Http/Middleware/EnsureMerchantOwnership.php
$store = $request->user()?->store;

if (! $store) {
    return ApiResponse::error('You do not have a store.', 403);
}

if (in_array($store->status, [MerchantStatus::Suspended, MerchantStatus::Banned])) {
    return ApiResponse::error('Your store has been suspended.', 403);
}
```

### Matrix akses berdasarkan store.status

| `store.status` | Akses `/api/merchant/*` | Produk muncul publik |
|---|---|---|
| `pending` | ✅ Ya (setup fase) | ❌ Tidak (admin belum approve) |
| `active` | ✅ Ya | ✅ Ya (jika produk `active`) |
| `suspended` | ❌ 403 | ❌ Tidak |
| `banned` | ❌ 403 | ❌ Tidak |

> **Desain intent:** `pending` merchant bisa setup toko + produk (tapi produk default `draft`).
> Setelah admin approve → `store.status = active` → produk bisa di-publish.

---

## Admin Gate

Diproteksi oleh `EnsureAdminRole` middleware:

```php
// app/Http/Middleware/EnsureAdminRole.php
if (! $request->user()?->isAdmin()) {
    return ApiResponse::error('Forbidden.', 403);
}
```

```php
// app/Models/User.php
public function isAdmin(): bool
{
    return $this->role === UserRole::Admin;
}
```

Tidak ada operasional gate untuk admin (admin selalu `active`).

---

## Middleware Aliases

Terdaftar di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'merchant' => \App\Http\Middleware\EnsureMerchantOwnership::class,
        'admin'    => \App\Http\Middleware\EnsureAdminRole::class,
    ]);
})
```

### Cara pakai di route

```php
// Merchant routes
Route::middleware(['auth:sanctum', 'merchant'])->group(function () { ... });

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () { ... });

// Auth only (buyer, merchant, admin semua bisa)
Route::middleware('auth:sanctum')->group(function () { ... });

// Public (tidak perlu auth)
Route::get('/products', ...);
```

---

## Proteksi Per-Resource (IDOR Prevention)

Untuk resource yang dimiliki user/toko tertentu, gunakan **Policy** — bukan middleware.

```php
// app/Policies/ProductPolicy.php — contoh
public function update(User $user, Product $product): bool
{
    return $product->store->user_id === $user->id;
}
```

Policy terdaftar via naming convention (auto-discovery) di Laravel 13 — tidak perlu `AuthServiceProvider`.

### Policy yang sudah ada

| Policy | Model | Guards |
|---|---|---|
| `ProductPolicy` | `Product` | `update`, `delete`, `manageMedia`, `manageVariants` |
| `AddressPolicy` | `Address` | `update`, `delete` (Sprint 2) |

---

## Admin Actions pada Merchant/Product

| Action | Cara |
|---|---|
| Suspend merchant | `$store->update(['status' => MerchantStatus::Suspended])` |
| Ban merchant | `$store->update(['status' => MerchantStatus::Banned])` |
| Approve merchant | `$store->update(['status' => MerchantStatus::Active, 'kyc_status' => KycStatus::Approved])` |
| Ban product | `$product->update(['status' => ProductStatus::Banned])` — admin endpoint, bukan merchant endpoint |
| Assign admin role | `$user->update(['role' => UserRole::Admin->value])` |

> **Catatan:** Admin tidak perlu mengubah `users.role` untuk suspend/ban merchant — cukup ubah `store.status`.
> `users.role = 'merchant'` tetap di-set, tapi middleware memblokir akses lewat `store.status`.

---

## Exception Handling Global

Di `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return \App\Http\Responses\ApiResponse::error('Resource not found.', 404);
    });
    $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e) {
        return \App\Http\Responses\ApiResponse::error('Forbidden.', 403);
    });
    $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
        return \App\Http\Responses\ApiResponse::validationError(
            'The given data was invalid.',
            $e->errors(),
        );
    });
})
```

---

## Cara Setup Role (Development)

```bash
# Via Tinker
docker compose exec app php artisan tinker

# Jadikan user sebagai admin
$user = \App\Models\User::where('email', 'admin@example.com')->first();
$user->update(['role' => \App\Enums\UserRole::Admin->value]);

# Cek role
$user->fresh()->role->value; // 'admin'
$user->fresh()->isAdmin();   // true
```

```php
// Via Factory (testing)
User::factory()->admin()->create();    // role = 'admin'
User::factory()->create();             // role = 'buyer' (default)

// Merchant user: register store via API atau:
$user  = User::factory()->create();
$store = Store::factory()->for($user)->create(); // role tetap 'buyer' di factory!
// → untuk test yang butuh role = 'merchant', set manual atau via API register
```

> **Catatan Factory:** `StoreFactory` tidak mengubah `users.role` — itu hanya dilakukan oleh
> `MerchantService::register()`. Jika test hanya butuh akses merchant endpoint (bukan asserting role),
> cukup buat store via factory. Jika test asserting `users.role = 'merchant'`, harus register via API.

---

## Seeder

```php
// database/seeders/AdminUserSeeder.php (dibuat Sprint 12)
\App\Models\User::factory()->admin()->create([
    'name'  => 'Super Admin',
    'email' => 'admin@marketplace.dev',
]);
```

---

## Files Terkait

```
app/Enums/UserRole.php
app/Http/Middleware/EnsureMerchantOwnership.php
app/Http/Middleware/EnsureAdminRole.php
app/Models/User.php                              → isAdmin(), role cast
app/Services/Merchant/MerchantService.php        → register() set role
bootstrap/app.php                                → middleware aliases + exception handlers
database/migrations/2026_05_07_175144_add_role_to_users_table.php
```
