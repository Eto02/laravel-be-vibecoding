# Shared Services — Planning
**Priority:** 🔴 P0 | **Status:** ⬜ Belum | **Sprint:** 0

> Harus dibuat sebelum modul lain karena semua modul bergantung pada shared services ini.

---

## Improvement Backlog

### 🔴 P0 — Wajib Sebelum Production Scale
- ⬜ **OtpService: Rate Limiting** — max 3 request OTP per identifier per 10 menit
- ⬜ **OtpService: Retry Limit** — max 5 percobaan verify; jika gagal, OTP diinvalidasi
- ⬜ **MediaService: Orphan Cleanup** — hapus file di R2 yang tidak pernah di-confirm dalam X menit

### 🟠 P1 — Architecture Improvement
- ⬜ **NotificationService → Event-driven** — ganti direct call dengan Laravel Events + Listeners
- ⬜ **CacheService: Interface Opsional** — tidak wajib interface, bisa inject `Cache` facade contract langsung jika tidak perlu swap provider
- ⬜ **MediaService: Upload Session Tracking** — track presigned URL yang sedang aktif (key, user_id, expires_at) untuk orphan detection

### 🟢 P2 — Scaling Optimization
- ⬜ **Queue semua notification type** — Email, Push, WA semuanya via Queue Jobs
- ⬜ **Central Logging Service** — audit trail terpusat (sudah ada `ProcessApiLog`, perlu formalisasi)
- ⬜ **Metrics (Prometheus)** — expose `/metrics` endpoint untuk Prometheus scraping (future)

---

## Yang Perlu Dibangun

| Service | Interface? | Priority |
|---|---|---|
| `EmailService` | ✅ Ya | 🔴 P0 |
| `OtpService` | ✅ Ya (rate limit + retry) | 🔴 P0 |
| `MediaService` | ✅ Ya (R2 + orphan cleanup) | 🔴 P0 |
| `IdempotencyService` | ✅ Ya (Redis-backed) | 🔴 P0 |
| `CacheService` | ❌ Tidak wajib (pakai native) | 🟠 P1 |
| `SmsService` | ✅ Ya | 🟡 P2 |
| `PushNotificationService` | ✅ Ya | 🟡 P2 |
| `AuditLogService` | ✅ Ya | 🟡 P2 |

---

## Service Details

### EmailService
- **Path:** `app/Services/Shared/EmailService.php`
- **Interface:** `app/Contracts/Shared/EmailServiceInterface.php`
- **Driver:** Laravel Mail via `MAIL_MAILER` env (`smtp`, `resend`, `ses`, `log`)
- **Queue:** Semua send via Queue — **tidak boleh synchronous** (P2 improvement)

```php
interface EmailServiceInterface {
    public function send(User $user, Mailable $mail): void;
    public function sendRaw(string $to, string $subject, string $body): void;
}
```

> **P2 Note:** Implementasi wajib dispatch `SendEmailJob` ke queue, bukan `Mail::send()` langsung.

---

### OtpService
- **Path:** `app/Services/Shared/OtpService.php`
- **Interface:** `app/Contracts/Shared/OtpServiceInterface.php`
- **Storage:** Redis

#### Redis Key Structure
```
otp:code:{identifier}       → hashed OTP value          TTL: 300s
otp:rate:{identifier}       → request count             TTL: 600s (10 menit window)
otp:retry:{identifier}      → failed verify count       TTL: 300s (sama dengan OTP)
```

#### P0 — Rate Limiting & Retry Limit
```php
interface OtpServiceInterface {
    /**
     * Generate OTP baru.
     * @throws OtpRateLimitException  jika sudah request >= 3x dalam 10 menit
     */
    public function generate(string $identifier): string;

    /**
     * Verify OTP.
     * @throws OtpExpiredException    jika OTP sudah kadaluarsa
     * @throws OtpMaxRetryException   jika sudah gagal >= 5x (OTP diinvalidasi otomatis)
     * @return bool
     */
    public function verify(string $identifier, string $otp): bool;

    /** Hapus OTP secara manual (misal setelah berhasil dipakai) */
    public function invalidate(string $identifier): void;

    /** Cek berapa sisa percobaan verify */
    public function remainingRetries(string $identifier): int;
}
```

#### Business Rules
| Rule | Nilai | Redis Key |
|---|---|---|
| Max OTP request per 10 menit | 3x | `otp:rate:{identifier}` |
| Max gagal verify sebelum invalidasi | 5x | `otp:retry:{identifier}` |
| OTP expiry | 300s (5 menit) | `otp:code:{identifier}` |
| Rate limit window | 600s (10 menit) | `otp:rate:{identifier}` |
| Hashing | `hash('sha256', $otp)` | — |
| Comparison | `hash_equals()` (constant-time) | — |

#### Custom Exceptions
```
app/Exceptions/Otp/OtpRateLimitException.php
app/Exceptions/Otp/OtpMaxRetryException.php
app/Exceptions/Otp/OtpExpiredException.php
```

---

### MediaService
- **Path:** `app/Services/Shared/MediaService.php`
- **Interface:** `app/Contracts/Shared/MediaServiceInterface.php`
- **Storage:** **Cloudflare R2** — S3-compatible, zero egress fee
- **Upload Strategy:** **Presigned URL (client-side direct upload)**
- **Local Dev:** Minio (S3-compatible, via Docker)

#### Upload Flow
```
1. POST /api/media/presigned-url  { folder: "products", filename: "photo.jpg", mime: "image/jpeg" }
   → Backend: generate UUID key, simpan upload session di Redis, return { upload_url, key, public_url }

2. Client: PUT {upload_url} — langsung ke R2, tidak melalui server PHP

3. POST /api/media/confirm  { key: "products/uuid.jpg" }
   → Backend: validasi file exists di R2, hapus upload session, return { public_url }
```

#### P0 — Orphan Cleanup
File yang di-presign tapi tidak pernah di-confirm dalam **15 menit** = orphan.

```
Redis key: media:session:{key}  → { user_id, folder, expires_at }   TTL: 900s
```

- **Artisan Command:** `php artisan media:cleanup-orphans`
- **Scheduler:** Jalankan setiap 15 menit via `Schedule::command('media:cleanup-orphans')->everyFifteenMinutes()`
- **Logic:** Ambil semua expired session keys dari Redis → cek apakah file ada di R2 → jika ada tapi belum di-confirm → delete dari R2

#### P1 — Upload Session Tracking
```php
// Setiap presigned URL yang digenerate, simpan session:
Redis::setex("media:session:{$key}", 900, json_encode([
    'user_id'    => $userId,
    'folder'     => $folder,
    'key'        => $key,
    'expires_at' => now()->addMinutes(15)->toISOString(),
    'confirmed'  => false,
]));
```

#### Interface
```php
interface MediaServiceInterface {
    /** Generate presigned PUT URL untuk client upload langsung ke R2 */
    public function generatePresignedUrl(
        string $folder,
        string $filename,
        string $mimeType,
        int $expiresInSeconds = 300
    ): array; // ['upload_url' => '...', 'key' => '...', 'public_url' => '...']

    /** Validasi file ada di R2, hapus upload session */
    public function confirmUpload(string $key): bool;

    /** Hapus file dari R2 */
    public function delete(string $key): bool;

    /** Presigned GET URL untuk file private */
    public function temporaryUrl(string $key, int $expiresInSeconds = 3600): string;

    /** Public URL untuk file public */
    public function publicUrl(string $key): string;

    /** Cleanup orphan files (dipanggil oleh Artisan command) */
    public function cleanupOrphans(): int; // returns jumlah file yang dihapus
}
```

#### Cloudflare R2 Config (`config/filesystems.php`)
```php
'r2' => [
    'driver'                  => 's3',
    'key'                     => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
    'secret'                  => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
    'region'                  => 'auto',
    'bucket'                  => env('CLOUDFLARE_R2_BUCKET'),
    'url'                     => env('CLOUDFLARE_R2_PUBLIC_URL'),
    'endpoint'                => 'https://'.env('CLOUDFLARE_R2_ACCOUNT_ID').'.r2.cloudflarestorage.com',
    'use_path_style_endpoint' => false,
],
```

#### Folder Conventions
| Folder | Konten | Visibility |
|---|---|---|
| `avatars/` | Foto profil user | Public |
| `store-assets/` | Logo & banner toko | Public |
| `products/` | Foto produk | Public |
| `reviews/` | Foto/video review | Public |
| `kyc-documents/` | KTP, NPWP merchant | **Private** (presigned GET only) |

#### Local Development — R2 Dev Bucket (Tanpa Minio)

> **Tidak perlu Minio.** Gunakan Cloudflare R2 bucket terpisah khusus dev.
> Free tier R2: **10GB storage + 1 juta request/bulan** — lebih dari cukup untuk development.

**Keuntungan R2 dev bucket vs Minio:**
- ✅ Tidak perlu container tambahan di Docker
- ✅ Presigned URL 100% identik dengan production (tidak ada perbedaan host/port)
- ✅ Tidak ada risiko *"works local, fails prod"*
- ✅ Setup lebih sederhana — cukup beda nilai env variable

**Setup:**
1. Buat bucket baru di Cloudflare R2: `marketplace-dev`
2. Generate API Token terpisah untuk bucket dev
3. Gunakan `.env` terpisah atau override env:

```env
# .env (development) — arahkan ke bucket dev
CLOUDFLARE_R2_BUCKET=marketplace-dev
CLOUDFLARE_R2_PUBLIC_URL=https://pub-xxx-dev.r2.dev

# .env.production — arahkan ke bucket production
CLOUDFLARE_R2_BUCKET=marketplace-prod
CLOUDFLARE_R2_PUBLIC_URL=https://media.yourdomain.com
```

> **Catatan:** `CLOUDFLARE_R2_ACCESS_KEY_ID` dan `CLOUDFLARE_R2_SECRET_ACCESS_KEY` bisa berbeda per environment, atau pakai satu token dengan permission ke kedua bucket.

---

### Cache Management (Simplified)

> **Keputusan Arsitektur:** Tidak perlu `CacheService` wrapper. Gunakan contract `Illuminate\Contracts\Cache\Repository` langsung di domain service.

```php
public function __construct(
    private readonly \Illuminate\Contracts\Cache\Repository $cache
) {}

// Usage:
$this->cache->remember('category:tree', 3600, fn() => ...);
```

#### TTL Conventions
| Data | TTL |
|---|---|
| Category tree | 3600s |
| Product list | 300s |
| User profile | 900s |
| Cart | 86400s |
| Ongkir rates | 3600s |
| Autocomplete | 1800s |

---

### IdempotencyService (🔴 P0)
- **Path:** `app/Services/Shared/IdempotencyService.php`
- **Interface:** `app/Contracts/Shared/IdempotencyServiceInterface.php`
- **Storage:** Redis (TTL 24 jam)

#### Logic
1. Cek `X-Idempotency-Key` di header.
2. Jika key ada di Redis: return cached response.
3. Jika tidak ada: kunci (lock), jalankan callback, simpan response, buka kunci.

```php
interface IdempotencyServiceInterface {
    /**
     * @param string $key Unique key dari client
     * @param callable $callback Logic yang harus dijalankan
     * @param int $ttl Default 86400 (24 jam)
     */
    public function check(string $key, callable $callback, int $ttl = 86400): mixed;
}
```

---

### Event-Driven Notifications (🟠 P1)

> ⚠️ **Arsitektur:** `NotificationService` dihapus. Gunakan Event-Listener untuk decoupling.

#### Flow
1. **Fire Event** (di Service): `event(new OrderShipped($order));`
2. **Listener** (di Domain/Shared):
   ```php
   class SendOrderShippedNotification implements ShouldQueue {
       public function handle($event) {
           // Kirim Email via EmailService
           // Kirim Push via PushNotificationService
           // Simpan In-app Notification model
       }
   }
   ```

#### P2 — Queue
Semua listener pengiriman notifikasi wajib `implements ShouldQueue` pada antrian `notifications`.

---

### AuditLogService (P2 — Central Logging)

> Formalisasi dari `ProcessApiLog` yang sudah ada. Semua audit event (login, order created, payment captured, admin action) masuk ke satu log terpusat.

- **Path:** `app/Services/Shared/AuditLogService.php`
- **Storage:** Tabel `api_logs` (sudah ada) + structured JSON log untuk Loki/Grafana
- **Dispatch:** Via Queue Job `ProcessApiLog` (sudah ada)

```php
// Usage dari manapun:
AuditLogService::log(
    event: 'order.created',
    userId: $user->id,
    resourceType: 'order',
    resourceId: $order->id,
    metadata: ['total' => $order->total],
);
```

#### Future: Prometheus Metrics (P2)
- Expose `/metrics` endpoint via `promphp/prometheus_client_php`
- Counter: `http_requests_total`, `otp_generated_total`, `payment_success_total`
- Histogram: `http_request_duration_seconds`
- Scrape via Prometheus → visualisasi di Grafana (selain Loki)

---

## Entities

| Storage | Keterangan |
|---|---|
| Redis | OTP sessions, upload sessions, cache |
| MySQL `api_logs` | Audit trail (sudah ada) |
| Cloudflare R2 | File storage |

---

## AppServiceProvider Bindings

```php
// Wajib
$this->app->bind(EmailServiceInterface::class, EmailService::class);
$this->app->bind(OtpServiceInterface::class, OtpService::class);
$this->app->bind(MediaServiceInterface::class, MediaService::class);
$this->app->bind(IdempotencyServiceInterface::class, IdempotencyService::class);
```

---

## Files to Create

```
# Services
app/Services/Shared/EmailService.php
app/Services/Shared/OtpService.php
app/Services/Shared/MediaService.php
app/Services/Shared/IdempotencyService.php
app/Services/Shared/AuditLogService.php        (P2)

# Contracts
app/Contracts/Shared/EmailServiceInterface.php
app/Contracts/Shared/OtpServiceInterface.php
app/Contracts/Shared/MediaServiceInterface.php
app/Contracts/Shared/IdempotencyServiceInterface.php

# Exceptions
app/Exceptions/Otp/OtpRateLimitException.php
app/Exceptions/Otp/OtpMaxRetryException.php
app/Exceptions/Otp/OtpExpiredException.php

# Artisan Commands
app/Console/Commands/Media/CleanupOrphansCommand.php

# API Endpoints (Media)
app/Http/Controllers/Api/Media/MediaController.php
app/Http/Requests/Media/GeneratePresignedUrlRequest.php
app/Http/Requests/Media/ConfirmUploadRequest.php
```

---

## Tests

```
tests/Unit/Services/Shared/EmailServiceTest.php
tests/Unit/Services/Shared/OtpServiceTest.php      # wajib test rate limit & retry
tests/Unit/Services/Shared/MediaServiceTest.php    # wajib test orphan cleanup
tests/Feature/Api/Media/MediaControllerTest.php
```

### OTP Test Cases yang Wajib Ada
```php
test_otp_rate_limit_throws_after_3_requests_in_10_minutes()
test_otp_verify_invalidates_after_5_failed_attempts()
test_otp_expires_after_300_seconds()
test_otp_verify_succeeds_with_valid_code()
test_otp_invalidated_after_successful_verify()
```

---

## Dependencies

### Packages
```bash
composer require league/flysystem-aws-s3-v3   # R2 & Minio driver
```

### Environment Variables
```env
# MediaService — Cloudflare R2
# Gunakan bucket & token BERBEDA untuk dev vs production
CLOUDFLARE_R2_ACCOUNT_ID=
CLOUDFLARE_R2_ACCESS_KEY_ID=
CLOUDFLARE_R2_SECRET_ACCESS_KEY=
CLOUDFLARE_R2_BUCKET=marketplace-dev        # ganti ke marketplace-prod di production
CLOUDFLARE_R2_PUBLIC_URL=                   # https://pub-xxx.r2.dev atau custom domain

# EmailService
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Marketplace"

# OtpService & CacheService (Redis)
REDIS_HOST=redis
REDIS_PORT=6379
```

### API Endpoints MediaService
```
POST   /api/media/presigned-url    [auth]  → generate URL + buat upload session
POST   /api/media/confirm          [auth]  → konfirmasi upload + hapus session
DELETE /api/media                  [auth]  → delete file dari R2
```
