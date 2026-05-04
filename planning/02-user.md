# MODULE 2 — User Profile
**Priority:** 🟠 P1 | **Status:** ⬜ Belum | **Sprint:** 2

---

## Yang Perlu Dibangun
- ⬜ Get/Update profile (nama, foto profil, bio, nomor HP)
- ⬜ Upload foto profil (via `MediaService`)
- ⬜ Address Book — CRUD alamat pengiriman, set default
- ⬜ Phone Verification via OTP (SMS/WhatsApp)
- ⬜ Notification Preferences (channel mana yang aktif)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `users` | `name`, `phone`, `phone_verified_at`, `avatar_url`, `bio` (extend) |
| `addresses` | `user_id`, `label`, `recipient_name`, `phone`, `province`, `city`, `district`, `postal_code`, `address`, `lat`, `lng`, `is_default` |
| `phone_verifications` | `user_id`, `phone`, `otp_hash`, `expires_at`, `verified_at` |

---

## Routes
```
GET  /api/users/me                             [auth]
PUT  /api/users/me                             [auth]
POST /api/users/me/avatar                      [auth]
GET  /api/users/me/addresses                   [auth]
POST /api/users/me/addresses                   [auth]
GET  /api/users/me/addresses/{id}              [auth]
PUT  /api/users/me/addresses/{id}              [auth]
DELETE /api/users/me/addresses/{id}            [auth]
POST /api/users/me/addresses/{id}/set-default  [auth]
POST /api/users/phone/send-otp                 [auth]
POST /api/users/phone/verify                   [auth]
```

---

## Files to Create
```
app/Http/Controllers/Api/User/UserController.php
app/Http/Controllers/Api/User/AddressController.php
app/Http/Requests/User/UpdateProfileRequest.php
app/Http/Requests/User/StoreAddressRequest.php
app/Http/Requests/User/UpdateAddressRequest.php
app/Http/Requests/User/VerifyPhoneRequest.php
app/Http/Resources/User/UserResource.php
app/Http/Resources/User/AddressResource.php
app/Services/User/UserService.php
app/Services/User/AddressService.php
app/Models/Address.php
database/migrations/xxxx_create_addresses_table.php
database/factories/AddressFactory.php
routes/api/user.php
tests/Feature/Api/User/UserTest.php
tests/Feature/Api/User/AddressTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `MediaService` | Upload foto profil ke S3 |
| `OtpService` | Generate & verify OTP nomor HP |
| `SmsService` | Kirim OTP via WA/SMS |

---

## Business Logic Notes
- Satu user hanya bisa punya satu `is_default = true` per waktu
- Set default: update semua address user ke `is_default = false`, lalu set yang dipilih ke `true`
- Phone OTP: satu nomor HP hanya bisa request OTP setiap 60 detik (rate limit via Redis)
- Avatar: resize ke max 500x500px sebelum upload
