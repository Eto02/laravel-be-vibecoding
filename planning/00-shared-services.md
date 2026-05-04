# Shared Services — Planning
**Priority:** 🔴 P0 | **Status:** ⬜ Belum | **Sprint:** 0

> Harus dibuat sebelum modul lain karena semua modul bergantung pada shared services ini.

---

## Yang Perlu Dibangun

- ⬜ `EmailService` + `EmailServiceInterface`
- ⬜ `OtpService` + `OtpServiceInterface`
- ⬜ `MediaService` + `MediaServiceInterface`
- ⬜ `CacheService` + `CacheServiceInterface`
- ⬜ `SmsService` + `SmsServiceInterface` (P2)
- ⬜ `PushNotificationService` + Interface (P2)
- ⬜ `NotificationService` + Interface (P2 — orchestrator)

---

## Service Details

### EmailService
- **Path:** `app/Services/Shared/EmailService.php`
- **Interface:** `app/Contracts/Shared/EmailServiceInterface.php`
- **Driver:** Laravel Mail via `MAIL_MAILER` env (`smtp`, `resend`, `ses`, `log`)
- **Responsibilities:**
  - Send a `Mailable` object to a user
  - Send raw email (subject + body)
  - All sends dispatch via Queue (`ShouldQueue`)

```php
interface EmailServiceInterface {
    public function send(User $user, Mailable $mail): void;
    public function sendRaw(string $to, string $subject, string $body): void;
}
```

### OtpService
- **Path:** `app/Services/Shared/OtpService.php`
- **Storage:** Redis (TTL: 300s / 5 menit)
- **Responsibilities:**
  - Generate 6-digit OTP
  - Store in Redis: key = `otp:{identifier}`, value = hashed OTP
  - Verify OTP (constant-time comparison)
  - Auto-delete setelah verify berhasil

```php
interface OtpServiceInterface {
    public function generate(string $identifier): string; // returns plain OTP
    public function verify(string $identifier, string $otp): bool;
    public function invalidate(string $identifier): void;
}
```

### MediaService
- **Path:** `app/Services/Shared/MediaService.php`
- **Storage:** S3 (via `AWS_*` env) atau Minio untuk local dev
- **Responsibilities:**
  - Upload file dari `UploadedFile`
  - Delete file by URL/path
  - Generate signed URL untuk private files

```php
interface MediaServiceInterface {
    public function upload(UploadedFile $file, string $disk, string $folder): string; // returns URL
    public function delete(string $url): bool;
    public function signedUrl(string $path, int $expiresInSeconds = 3600): string;
}
```

### CacheService
- **Path:** `app/Services/Shared/CacheService.php`
- **Storage:** Redis
- **TTL Conventions:**
  - Category tree: `3600s`
  - Product list: `300s`
  - User profile: `900s`
  - Cart: `86400s`
  - OTP: `300s` (handled by OtpService)

```php
interface CacheServiceInterface {
    public function remember(string $key, int $ttl, callable $callback): mixed;
    public function forget(string $key): void;
    public function forgetByPattern(string $pattern): void; // e.g. 'products:*'
    public function tags(array $tags): static; // tag-based cache invalidation
}
```

---

## Entities
Tidak membutuhkan tabel sendiri — menggunakan Redis dan Laravel Mail infrastructure.

## AppServiceProvider Bindings
```php
$this->app->bind(EmailServiceInterface::class, EmailService::class);
$this->app->bind(OtpServiceInterface::class, OtpService::class);
$this->app->bind(MediaServiceInterface::class, MediaService::class);
$this->app->bind(CacheServiceInterface::class, CacheService::class);
```

## Tests
- `tests/Unit/Services/Shared/EmailServiceTest.php`
- `tests/Unit/Services/Shared/OtpServiceTest.php`
- `tests/Unit/Services/Shared/MediaServiceTest.php`
- `tests/Unit/Services/Shared/CacheServiceTest.php`

## Dependencies
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` (untuk MediaService)
- `MAIL_MAILER`, `MAIL_FROM_ADDRESS` (untuk EmailService)
- `REDIS_HOST`, `REDIS_PORT` (untuk OtpService, CacheService)
