# MODULE 1 — Auth & Identity
**Priority:** 🔴 P0 | **Status:** 🟡 Partial | **Sprint:** 1

---

## Yang Sudah Ada ✅
- Register, Login (email/password)
- OAuth (Google, GitHub via Socialite)
- Refresh Token Rotation (custom)
- `GET /api/auth/me`
- `POST /api/auth/logout`

## Yang Perlu Dibangun ⬜
- ⬜ Email Verification — kirim link saat register, blokir login jika belum verify
- ⬜ Resend Verification Email
- ⬜ Forgot Password — kirim reset link via email
- ⬜ Reset Password — validasi token + simpan password baru
- ⬜ Change Password — untuk user yang sudah login
- ⬜ Active Session List
- ⬜ Logout dari device tertentu (by session ID)

---

## Entities
| Tabel | Keterangan |
|---|---|
| `users` | Sudah ada |
| `refresh_tokens` | Sudah ada |
| `oauth_accounts` | Sudah ada |
| `password_reset_tokens` | Sudah ada (Laravel default) |

---

## Routes
```
# Existing
POST /api/auth/register
POST /api/auth/login
POST /api/auth/refresh
POST /api/auth/logout          [auth]
GET  /api/auth/me              [auth]
POST /api/auth/oauth/{provider}

# To Add
POST /api/auth/email/verify
POST /api/auth/email/resend
POST /api/auth/forgot-password
POST /api/auth/reset-password
PUT  /api/auth/change-password [auth]
GET  /api/auth/sessions        [auth]
DELETE /api/auth/sessions/{id} [auth]
```

---

## Files to Create/Update
```
app/Http/Controllers/Api/Auth/AuthController.php     (update)
app/Http/Requests/Auth/ForgotPasswordRequest.php     (new)
app/Http/Requests/Auth/ResetPasswordRequest.php      (new)
app/Http/Requests/Auth/ChangePasswordRequest.php     (new)
app/Http/Requests/Auth/VerifyEmailRequest.php        (new)
app/Services/Auth/AuthService.php                    (update)
app/Mail/Auth/EmailVerificationMail.php              (new)
app/Mail/Auth/PasswordResetMail.php                  (new)
routes/api/auth.php                                  (new file)
tests/Feature/Api/Auth/AuthTest.php                  (update)
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `EmailService` | Kirim `EmailVerificationMail`, `PasswordResetMail` |
| `OtpService` | Opsional — jika verifikasi via OTP, bukan link |

---

## Business Logic Notes
- Email verification link: signed URL dengan expiry 60 menit
- Password reset token: stored in `password_reset_tokens`, expiry 60 menit
- Reset token harus dihapus setelah dipakai (single-use)
- Change password: wajib input `current_password` untuk verifikasi
