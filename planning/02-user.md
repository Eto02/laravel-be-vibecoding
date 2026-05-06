# MODULE 2 — User Profile
**Priority:** 🟠 P1 | **Status:** 🔄 In Progress | **Sprint:** 2

---

## Yang Perlu Dibangun
- ⬜ Get/Update profile (nama, foto profil, bio) — phone hanya via OTP flow
- ⬜ Upload foto profil (via `MediaService` — presigned URL, resize di client)
- ⬜ Address Book — CRUD alamat pengiriman, set default
- ⬜ Phone Verification via OTP (SMS/WhatsApp via `SmsService`)

> **Defer ke modul 10:** Notification Preferences (channel mana yang aktif)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `users` | `name`, `phone` (E.164), `phone_verified_at`, `avatar`, `bio` (extend via migration) |
| `addresses` | `user_id`, `label`, `recipient_name`, `phone`, `province`, `city`, `district`, `postal_code`, `street`, `lat`, `lng`, `is_default`, `deleted_at` |
| `phone_verifications` | `id`, `user_id`, `phone`, `otp_hash` (SHA-256), `expires_at`, `verified_at`, `ip_address`, `user_agent`, `created_at` |

> **Catatan:**
> - Kolom `avatar` sudah ada di `users` (dari OAuth migration). Extend via migration baru untuk `phone`, `phone_verified_at`, `bio`.
> - Kolom isi jalan di `addresses` dinamai `street` (bukan `address`) untuk menghindari ambiguitas dengan nama tabel.
> - `addresses` menggunakan soft delete (`deleted_at`) untuk preservasi history Order.
> - `otp_hash` menggunakan SHA-256 (konsisten dengan `OtpService` yang sudah ada, bukan bcrypt).

---

## Routes
```
# Profile
GET  /api/users/me                              [auth]   ← full profile (beda scope dari GET /api/auth/me)
PUT  /api/users/me                              [auth]   ← hanya name, bio (phone via OTP flow)
POST /api/users/me/avatar                       [auth]

# Address Book
GET    /api/users/me/addresses                  [auth]
POST   /api/users/me/addresses                  [auth]
GET    /api/users/me/addresses/{id}             [auth]
PUT    /api/users/me/addresses/{id}             [auth]
DELETE /api/users/me/addresses/{id}             [auth]
POST   /api/users/me/addresses/{id}/set-default [auth]

# Phone Verification
POST /api/users/phone/send-otp                  [auth]
POST /api/users/phone/verify                    [auth]
```

> `GET /api/auth/me` tetap ada (basic fields untuk token context). `GET /api/users/me` return full profile.

---

## Files to Create
```
# Controllers
app/Http/Controllers/Api/User/UserController.php
app/Http/Controllers/Api/User/AddressController.php

# Requests
app/Http/Requests/User/UpdateProfileRequest.php      ← hanya name, bio (no phone)
app/Http/Requests/User/UploadAvatarRequest.php        ← validate mime: jpeg/png/webp only
app/Http/Requests/User/StoreAddressRequest.php
app/Http/Requests/User/UpdateAddressRequest.php
app/Http/Requests/User/SendPhoneOtpRequest.php
app/Http/Requests/User/VerifyPhoneRequest.php

# Resources
app/Http/Resources/User/UserResource.php              ← full profile fields
app/Http/Resources/User/AddressResource.php

# Policy
app/Policies/AddressPolicy.php                        ← IDOR prevention untuk address endpoints

# Services
app/Services/User/UserService.php
app/Services/User/AddressService.php

# Shared Services (buat sebelum implementasi phone OTP)
app/Contracts/Shared/SmsServiceInterface.php
app/Services/Shared/SmsService.php

# Exceptions
app/Exceptions/User/PhoneAlreadyTakenException.php

# Models & DB
app/Models/Address.php                               ← SoftDeletes
app/Models/PhoneVerification.php
database/migrations/xxxx_add_profile_fields_to_users_table.php
database/migrations/xxxx_create_addresses_table.php
database/migrations/xxxx_create_phone_verifications_table.php
database/factories/AddressFactory.php
database/factories/PhoneVerificationFactory.php

# Routes
routes/api/user.php

# Tests
tests/Feature/Api/User/UserTest.php
tests/Feature/Api/User/AddressTest.php
tests/Feature/Api/User/PhoneVerificationTest.php
tests/Unit/Services/User/UserServiceTest.php
tests/Unit/Services/User/AddressServiceTest.php
tests/Unit/Services/Shared/SmsServiceTest.php
```

---

## Shared Services Needed
| Service | Status | Kegunaan |
|---|---|---|
| `MediaService` | ✅ Ada | Generate presigned URL untuk upload foto profil |
| `OtpService` | ✅ Ada | Generate & verify OTP (Redis SHA-256, TTL 5 min) |
| `SmsService` | ⬜ Belum | Kirim OTP via WA/SMS (Fonnte/Twilio) |

> `SmsService` harus dibuat + di-bind di `AppServiceProvider` sebelum implementasi phone OTP.

---

## User/UserResource — Field Whitelist
```php
'id'                => $this->id,
'name'              => $this->name,
'email'             => $this->email,
'email_verified_at' => $this->email_verified_at?->toISOString(),
'phone'             => $this->phone,
'phone_verified_at' => $this->phone_verified_at?->toISOString(),
'avatar'            => $this->avatar,
'bio'               => $this->bio,
'created_at'        => $this->created_at?->toISOString(),
```

---

## Business Logic Notes

### Profile Update
- `PUT /me` hanya izinkan field: `name`, `bio` — phone TIDAK boleh diupdate via endpoint ini
- Jika di masa depan user diizinkan ganti phone lewat `PUT /me`, wajib reset `phone_verified_at = null`

### Avatar Upload
- Server validate MIME di `UploadAvatarRequest`: hanya `image/jpeg`, `image/png`, `image/webp`
- Flow: generate presigned URL → client PUT ke R2 → confirm upload → update `users.avatar`
- Client wajib resize sebelum upload (max 500×500px) — server tidak bisa resize (presigned URL flow)

### Address Book
- Satu user hanya bisa punya satu `is_default = true` per waktu
- Set default: jalankan dalam satu DB transaction — reset semua ke `false`, set target ke `true`
- Ownership check via `AddressPolicy` pada semua endpoint `{id}` — cegah IDOR
- Soft delete: `DELETE /me/addresses/{id}` hanya set `deleted_at`, tidak hapus fisik (preservasi order history)

### Phone Verification
- Phone disimpan dalam format E.164 (`+62xxx`) — normalisasi sebelum simpan & jadi OTP identifier
- OTP identifier format: `phone:{e164_number}` — namespace agar tidak collision dengan email OTP
- `send-otp` flow:
  1. Validate format E.164
  2. Cek phone belum dipakai user lain (`PhoneAlreadyTakenException` jika sudah)
  3. Generate OTP via `OtpService::generate("phone:{phone}")` (Redis, TTL 5 min)
  4. Kirim via `SmsService`
  5. Simpan record ke `phone_verifications` dengan `otp_hash` (SHA-256) untuk audit trail + `ip_address`, `user_agent`
- `verify` flow:
  1. `OtpService::verify("phone:{phone}", $otp)`
  2. Update `users.phone` dan `users.phone_verified_at = now()`
  3. Set `phone_verifications.verified_at = now()` pada record terkait
- Rate limit: enforced di `OtpService` Redis layer (3x request per 10 menit window)

### OTP Exceptions — harus ditangani di `bootstrap/app.php`
```
OtpRateLimitException  → 429 Too Many Requests
OtpMaxRetryException   → 422 Unprocessable
OtpExpiredException    → 422 Unprocessable
PhoneAlreadyTakenException → 422 Unprocessable
```

---

## Urutan Implementasi
1. Update `bootstrap/app.php` — tambah OTP exception handlers
2. Buat `SmsService` + `SmsServiceInterface` + bind di `AppServiceProvider`
3. Migrations: extend `users` + buat `addresses` + `phone_verifications`
4. Models: `Address` (SoftDeletes), `PhoneVerification`, update `User` (fillable, casts, relations)
5. Exceptions: `PhoneAlreadyTakenException`
6. Services: `UserService`, `AddressService`
7. Policy: `AddressPolicy`
8. Controllers + Requests + Resources
9. Routes + update `routes/api.php` loader
10. Tests
