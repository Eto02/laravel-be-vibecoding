# Payment SOT Hardening — Dual Verification & Active Reconciliation

**Status:** ✅ Selesai
**Branch:** `fix/payment-sot-hardening` (dari `main` setelah `fix/cancel-order-on-payment-expire` merged)
**Affects:** Sprint 7 — Payment Module

---

## Problem Statement

Sistem saat ini menjadikan **gateway webhook sebagai Source of Truth**. Ini berbeda dari praktik marketplace besar (Tokopedia, Shopee, Amazon) yang menggunakan **internal state machine sebagai SOT** dan gateway hanya sebagai *input signal* yang diverifikasi aktif sebelum state berubah.

### Kelemahan Arsitektur Saat Ini

| Risiko | Dampak | Ada sekarang? |
|---|---|---|
| Webhook forged/dimanipulasi | Signature check bisa dibypass jika secret bocor | Parsial ✅ |
| Webhook delay / tidak datang | Payment stuck Pending selamanya | ❌ |
| Webhook dikirim 2x oleh gateway | Double processing | Parsial ✅ |
| Gateway bug — kirim status salah | State kita salah tanpa tahu | ❌ |
| Dual verify + sync = bottleneck | Timeout/race condition di bawah load | ❌ |
| Concurrent webhook + reconcile | Double update tanpa lock | ❌ |

---

## Keputusan Arsitektur (Finalized)

### Q1: Async Webhook Processing → **Implement sekarang**
Dual verification menambah HTTP call ke gateway API di dalam webhook handler. Jika sync, ini menjadi bottleneck di bawah load dan single point of failure. Semua marketplace besar (Tokopedia, Shopee, Lazada) proses webhook async tanpa terkecuali. Implement bersamaan dengan dual verification dalam satu branch.

### Q2: Reconciliation Scope → **Hanya overdue payments**
Scope Phase 4 hanya payment `Pending` dengan `expires_at < now()` yang belum diupdate. Long-pending anomaly detection (payment tanpa `expires_at` atau threshold lama) masuk **Lapisan 2 — Settlement Reconciliation** di Sprint 12 (Admin/Finance module, settlement report API dari gateway).

### Q3: Dual Verify Failure → **Moot — diselesaikan oleh Q1**
Dengan async processing, job failure di-handle oleh queue retry dengan exponential backoff. Tidak perlu keputusan skip vs fallback. Jika semua retry habis → job masuk dead-letter queue → reconciliation job tangkap di run berikutnya.

---

## Target Arsitektur

```
┌─────────────────────────────────────────────────────────────┐
│              INTERNAL STATE MACHINE (DB) = SOT              │
│                                                             │
│  State hanya berubah melalui applyStatusTransition()        │
│  dengan DB lockForUpdate() — tidak ada exception            │
└──────────────────────┬──────────────────────────────────────┘
                       │ trigger dari 2 sumber:
           ┌───────────┴─────────────┐
           │                         │
  ① Webhook (passive)       ② ReconcilePayments Job (active)
  Gateway → Handler         Kita poll gateway tiap 5 menit
  verifyWebhook()            untuk semua overdue Pending
  dispatch Job               payments
  return 200 (<5ms)
           │                         │
           ▼                         │
  ProcessWebhookJob (queue)          │
  [retry on failure]                 │
           │                         │
           └────────────┬────────────┘
                        │
                        ▼
             getPaymentStatus(gatewayRef)
             parseStatusResponse(response)
                        │
               ┌────────┴────────┐
               │  API OK?        │
               └────────┬────────┘
           ┌────────────┴──────────────┐
         GAGAL                      BERHASIL
           │                           │
    job retry (queue             gunakan API status
    exponential backoff)         sebagai SOT
    eventual reconciliation      (bukan webhook payload)
                                        │
                                        ▼
                             applyStatusTransition()
                             dengan lockForUpdate()
```

---

## Phases

### Phase 0 — Async Webhook Processing (Prerequisite)

Ubah webhook handler menjadi **fire-and-forget**: verifikasi signature, dispatch job, return 200 langsung.

**File baru:** `app/Jobs/Payment/ProcessWebhookJob.php`
```php
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    // Progressive backoff — bukan fixed. int $backoff tidak exponential.
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300]; // 10s, 30s, 1m, 2m, 5m
    }

    public function __construct(
        private readonly string $provider,
        private readonly array  $payload, // hanya body — parseWebhookPayload() tidak butuh headers
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        // Rebuild synthetic request dari stored payload
        $request = Request::create('/', 'POST', [], [], [], [], json_encode($this->payload));
        $request->headers->set('Content-Type', 'application/json');

        $paymentService->handleWebhook($request, $this->provider);
    }
}
```

**File:** `app/Http/Controllers/Api/Payment/WebhookController.php`
```php
// SEBELUM:
public function xendit(Request $request): JsonResponse
{
    $this->payment->handleWebhook($request, 'xendit');
    return ApiResponse::success('Webhook received.');
}

// SESUDAH:
public function xendit(Request $request): JsonResponse
{
    // Verify signature synchronously — tolak webhook palsu sebelum masuk queue
    $gateway = app('payment.xendit');
    if (! $gateway->verifyWebhook($request)) {
        return ApiResponse::error('Invalid webhook signature.', 403);
    }

    ProcessWebhookJob::dispatch('xendit', $request->all());
    return ApiResponse::success('Webhook received.');
}
// Sama untuk midtrans()
```

> **Catatan:** `verifyWebhook()` tetap **sync di controller** — tolak webhook palsu sebelum masuk queue. Dual verification (API call ke gateway) dilakukan di dalam job.

**`handleWebhook()` di PaymentService:** hapus `verifyWebhook()` call — signature tetap `handleWebhook(Request $request, string $provider)` agar bisa dipanggil dari job dengan synthetic request.

**Testing:** Tambah `QUEUE_CONNECTION=sync` ke `phpunit.xml` agar `ProcessWebhookJob` dijalankan inline saat test — semua `WebhookTest` yang ada tetap pass tanpa perubahan.
```xml
<!-- phpunit.xml -->
<env name="QUEUE_CONNECTION" value="sync"/>
```

---

### Phase 1 — Interface + Normalization Foundation

Tambah method `parseStatusResponse()` ke interface untuk normalize raw API response ke format internal yang sama dengan webhook payload.

**File:** `app/Services/Payment/PaymentGatewayInterface.php`
```php
/**
 * Normalize a raw gateway API response (from getPaymentStatus()) into
 * the internal status format used by parseWebhookPayload().
 *
 * @return array{status: string, amount: int}
 *   status: 'paid' | 'expired' | 'failed' | 'pending'
 *   amount: integer cents
 */
public function parseStatusResponse(array $apiResponse): array;
```

**File:** `app/Services/Payment/XenditPaymentService.php`
```php
// Xendit invoice status: PENDING, PAID, SETTLED, EXPIRED
public function parseStatusResponse(array $apiResponse): array
{
    $raw = strtoupper($apiResponse['status'] ?? '');

    $status = match ($raw) {
        'PAID', 'SETTLED' => 'paid',
        'EXPIRED'         => 'expired',
        'PENDING'         => 'pending',
        default           => 'failed',
    };

    return [
        'status' => $status,
        'amount' => (int) round((float) ($apiResponse['paid_amount'] ?? $apiResponse['amount'] ?? 0) * 100),
    ];
}
```

**File:** `app/Services/Payment/MidtransPaymentService.php`
```php
// Midtrans transaction_status: pending, capture, settlement, deny, cancel, expire, failure
public function parseStatusResponse(array $apiResponse): array
{
    $transactionStatus = $apiResponse['transaction_status'] ?? '';
    $fraudStatus       = $apiResponse['fraud_status'] ?? '';

    $isPaid = ($transactionStatus === 'capture' && $fraudStatus === 'accept')
        || $transactionStatus === 'settlement';

    $status = match (true) {
        $isPaid                                                    => 'paid',
        in_array($transactionStatus, ['cancel', 'expire', 'deny']) => 'expired',
        $transactionStatus === 'failure'                           => 'failed',
        $transactionStatus === 'pending'                           => 'pending',
        default                                                    => 'failed',
    };

    return [
        'status' => $status,
        'amount' => (int) round((float) ($apiResponse['gross_amount'] ?? 0) * 100),
    ];
}
```

**Tests (Unit):**
- `tests/Unit/Services/Payment/XenditParseStatusResponseTest.php`
- `tests/Unit/Services/Payment/MidtransParseStatusResponseTest.php`
- Covers: setiap status value → normalized correctly, unknown → 'failed'

---

### Phase 2 — DB Locking + Shared State Transition (termasuk Recovery Logic)

**Problem:** `markPaid()`, `markExpired()`, `markFailed()` tidak ada locking. Webhook job dan reconciliation job bisa run concurrent untuk payment yang sama → double update.

**Keputusan:** Soft-terminal recovery logic (smart recovery + wallet credit) masuk ke `applyStatusTransition()` agar berlaku untuk **semua jalur** — webhook job maupun reconciliation job.

**Solusi:** Satu unified method `applyStatusTransition()` dengan `DB::transaction + lockForUpdate` yang mencakup seluruh terminal guard logic.

**File:** `app/Services/Payment/PaymentService.php`

```php
/**
 * Single entry point for ALL payment state transitions.
 * DB lock prevents concurrent processing (webhook job + reconciliation race).
 * Contains complete terminal guard: hard-terminal + soft-terminal recovery.
 */
private function applyStatusTransition(Payment $payment, string $status, int $amount): void
{
    DB::transaction(function () use ($payment, $status, $amount) {
        $locked = Payment::where('id', $payment->id)
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            return;
        }

        // Hard-terminal: Paid dan Refunded tidak bisa diubah oleh siapapun
        if (in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::Refunded])) {
            return;
        }

        // Soft-terminal: Expired/Failed + status paid → recovery atau double-charge
        if (in_array($locked->status, [PaymentStatus::Expired, PaymentStatus::Failed]) && $status === 'paid') {
            $order = $locked->order;

            if ($order && $order->status === OrderStatus::Pending) {
                // Recovery: uang masuk, order belum fulfilled → selesaikan
                Log::warning('Paid signal for locally-expired payment — order still pending, recovering', [
                    'payment_id'   => $locked->id,
                    'gateway'      => $locked->gateway,
                    'local_status' => $locked->status->value,
                ]);
                $this->cancelPendingPaymentsForOrder($order);
                $this->markPaid($locked, $amount);
                return;
            }

            // Double-charge: order sudah fulfilled → kembalikan ke wallet
            $this->refundDoubleChargeToWallet($locked, $amount, $locked->gateway_ref);
            return;
        }

        // Normal transition
        match ($status) {
            'paid'    => $this->markPaid($locked, $amount),
            'expired' => $this->markExpired($locked),
            default   => $this->markFailed($locked),
        };
    });
}
```

`markPaid()`, `markExpired()`, `markFailed()`, `refundDoubleChargeToWallet()` tetap private helpers. Semua call site di `handleWebhook()` yang sebelumnya memanggil mark* langsung diganti ke `applyStatusTransition()`.

**Efek:** Logika yang sebelumnya tersebar di `handleWebhook()` kini terpusat — webhook job dan reconciliation job keduanya mendapat perlindungan yang sama.

---

### Phase 3 — Dual Verification di Webhook Handler

`handleWebhook()` sekarang tidak perlu `verifyWebhook()` (sudah di controller). Tambah dual verification via API call.

**File:** `app/Services/Payment/PaymentService.php`

```
FLOW BARU handleWebhook():

  1. parseWebhookPayload() → $webhookStatus, $externalId
  2. Cari $payment by gatewayRef
  3. Cek hard-terminal (Paid/Refunded) → return
  4. getPaymentStatus($gatewayRef) → $apiResponse
     - Jika gagal (exception/timeout):
         Log::warning('Dual verification failed — deferring to reconciliation')
         throw exception → job retry
  5. parseStatusResponse($apiResponse) → $apiStatus, $apiAmount
  6. Jika $webhookStatus !== $apiStatus:
         Log::warning('Webhook/API status mismatch — trusting API', [...])
  7. applyStatusTransition($payment, $apiStatus, $apiAmount)
```

Config timeout:
```php
// config/payment.php
'dual_verification_timeout_seconds' => (int) env('PAYMENT_DUAL_VERIFY_TIMEOUT', 5),
```

HTTP call dengan timeout:
```php
Http::timeout(config('payment.dual_verification_timeout_seconds', 5))
    ->withBasicAuth(...)
    ->get(...);
```

---

### Phase 4 — ReconcilePayments Job

**File:** `app/Jobs/Payment/ReconcilePayments.php`

```
Target: Payment Pending yang berada di "gap window" PER GATEWAY
  (mirip expireUnpaidPayments() yang sudah ada)

Query: per gateway, gunakan WHERE yang sama dengan expiry_grace_minutes config:
  Xendit:   expires_at < now() AND expires_at > now() - 30min
  Midtrans: expires_at < now() AND expires_at > now() - 1440min

Pembangunan query — sama pattern dengan expireUnpaidPayments():
  Payment::where('status', Pending)
    ->where(function($q) {
        foreach (config('payment.expiry_grace_minutes') as $gateway => $grace) {
            $q->orWhere(function($q2) use ($gateway, $grace) {
                $q2->where('gateway', $gateway)
                   ->where('expires_at', '<', now())
                   ->where('expires_at', '>', now()->subMinutes($grace));
            });
        }
    })
    ->limit(config('payment.reconcile_batch_size', 50))
    ->get()

Logic per payment:
  getPaymentStatus(gateway_ref)  ← HTTP call dengan timeout 5s
  parseStatusResponse()
  'paid'    → applyStatusTransition('paid')    ← VA yang dibayar user
  'expired' → applyStatusTransition('expired') ← konfirmasi dari gateway
  'failed'  → applyStatusTransition('failed')
  'pending' → skip (gateway bilang masih aktif, tunggu)
  API error → skip + Log::warning (akan di-retry di run berikutnya)

Batasan: max 50 payment per run (PAYMENT_RECONCILE_BATCH)
```

**File:** `routes/console.php`
```php
Schedule::job(new \App\Jobs\Payment\ReconcilePayments)->everyFiveMinutes();
```

**Tests:**
- Payment overdue + API `paid` → markPaid, PaymentCaptured dispatched
- Payment overdue + API `expired` → markExpired, PaymentFailed dispatched
- Payment overdue + API `pending` → tidak ada perubahan
- Payment overdue + API error → tidak ada perubahan, Log::warning
- Payment belum overdue → tidak disentuh reconciliation
- Batch limit: hanya 50 per run

---

### Phase 5 — Config Final

**File:** `config/payment.php`
```php
return [
    'default_gateway'  => env('PAYMENT_GATEWAY', 'xendit'),
    'expiry_minutes'   => (int) env('PAYMENT_EXPIRY_MINUTES', 15),

    // Scheduler safety net: hanya fire setelah expires_at + grace_minutes
    // Webhook + reconciliation seharusnya sudah handle sebelum ini
    'expiry_grace_minutes' => [
        'xendit'   => (int) env('PAYMENT_EXPIRY_GRACE_XENDIT', 30),
        'midtrans' => (int) env('PAYMENT_EXPIRY_GRACE_MIDTRANS', 1440),
    ],

    // Timeout untuk HTTP call ke gateway API (dual verification + reconciliation)
    'dual_verification_timeout_seconds' => (int) env('PAYMENT_DUAL_VERIFY_TIMEOUT', 5),

    // Maksimal payment yang di-reconcile per run
    'reconcile_batch_size' => (int) env('PAYMENT_RECONCILE_BATCH', 50),
];
```

**File:** `.env.example`
```env
PAYMENT_EXPIRY_MINUTES=15
PAYMENT_EXPIRY_GRACE_XENDIT=30
PAYMENT_EXPIRY_GRACE_MIDTRANS=1440
PAYMENT_DUAL_VERIFY_TIMEOUT=5
PAYMENT_RECONCILE_BATCH=50
```

---

## Alur Lengkap Setelah Semua Phases

```
PAYMENT LIFECYCLE — POST HARDENING:

T+0m   → User initiate payment → Payment Pending, expires_at = T+15m

T+?m   → User bayar (di Xendit invoice / Midtrans Snap)
         Gateway kirim webhook PAID ke /api/webhooks/xendit
         Handler: verifyWebhook() ✅ → dispatch ProcessWebhookJob → return 200
         Job: getPaymentStatus() → API bilang PAID → applyStatusTransition('paid')
         → Payment Paid, Order Paid ✅

T+15m  → expires_at tercapai (user belum bayar)
         Gateway kirim webhook EXPIRED
         Handler: dispatch ProcessWebhookJob → return 200
         Job: getPaymentStatus() → API bilang EXPIRED → applyStatusTransition('expired')
         → Payment Expired, Order Cancelled ✅

T+20m  → ReconcilePayments job jalan (kalau webhook belum datang / delayed)
         Lihat Payment Pending dengan expires_at = T+15m → overdue
         getPaymentStatus() → API bilang EXPIRED → applyStatusTransition('expired')
         (applyStatusTransition() idempotent via lockForUpdate — aman kalau sudah expired)

T+45m  → ExpireUnpaidPayments scheduler (last resort, grace 30min)
         Masih Pending? → markExpired langsung tanpa API call
         Dalam praktik: hampir tidak pernah sampai sini karena reconciliation sudah handle

SPECIAL CASES:
  Webhook + Reconciliation concurrent → lockForUpdate() → hanya satu yang process
  Webhook API timeout → job retry (5x, exponential backoff) → reconciliation handle
  Webhook status ≠ API status → Log::warning + gunakan API status
  Double charge → wallet credit + Log::critical (sudah ada dari branch sebelumnya)
```

---

## Files yang Berubah

| File | Jenis |
|---|---|
| `app/Jobs/Payment/ProcessWebhookJob.php` | **New** — async webhook processor |
| `app/Jobs/Payment/ReconcilePayments.php` | **New** — active reconciliation |
| `app/Http/Controllers/Api/Payment/WebhookController.php` | Update — dispatch job, verifyWebhook sync |
| `app/Services/Payment/PaymentGatewayInterface.php` | Add `parseStatusResponse()` |
| `app/Services/Payment/XenditPaymentService.php` | Implement `parseStatusResponse()` |
| `app/Services/Payment/MidtransPaymentService.php` | Implement `parseStatusResponse()` |
| `app/Services/Payment/PaymentService.php` | Dual verify, `applyStatusTransition()`, remove `verifyWebhook()` call |
| `routes/console.php` | Register `ReconcilePayments` |
| `config/payment.php` | Tambah config keys |
| `.env.example` | Tambah env vars |
| `tests/Unit/Services/Payment/XenditParseStatusResponseTest.php` | **New** |
| `tests/Unit/Services/Payment/MidtransParseStatusResponseTest.php` | **New** |
| `tests/Feature/Api/Payment/WebhookDualVerificationTest.php` | **New** |
| `tests/Feature/Jobs/Payment/ReconcilePaymentsTest.php` | **New** |

---

## Resolved Gaps (dari plan-review)

| Gap | Resolusi |
|---|---|
| Soft-terminal recovery logic hilang dari `applyStatusTransition()` | Recovery + wallet credit dipindah ke dalam `applyStatusTransition()` — berlaku untuk webhook job DAN reconciliation |
| Existing WebhookTest rusak setelah async | `QUEUE_CONNECTION=sync` ditambah ke `phpunit.xml` — job dijalankan inline saat test |
| ReconcilePayments tidak per-gateway | Query menggunakan pattern yang sama dengan `expireUnpaidPayments()` — per-gateway grace window |
| `$backoff = 10` tidak exponential | Diganti dengan `backoff(): array` yang returns `[10, 30, 60, 120, 300]` |
| Headers di job tidak diperlukan | Job hanya simpan `$request->all()` (body) — `parseWebhookPayload()` tidak butuh headers |
| `handleWebhook()` signature setelah remove `verifyWebhook()` | Signature tetap `handleWebhook(Request $request, string $provider)`, hanya `verifyWebhook()` call yang dihapus dari dalamnya |

---

## Definition of Done

- [x] `QUEUE_CONNECTION=sync` ditambah ke `phpunit.xml`
- [x] `ProcessWebhookJob` — async, progressive backoff, hanya simpan payload body
- [x] `WebhookController` — `verifyWebhook()` sync, dispatch `ProcessWebhookJob`
- [x] `parseStatusResponse()` implemented dan unit tested untuk Xendit dan Midtrans
- [x] `applyStatusTransition()` dengan DB `lockForUpdate()` + soft-terminal recovery logic terpusat
- [x] `handleWebhook()` — `verifyWebhook()` dihapus, dual verification via `getPaymentStatus()`
- [x] `ReconcilePayments` job — per-gateway gap window query, batch 50, every 5 menit
- [x] Semua test lama tetap pass (berkat `QUEUE_CONNECTION=sync`)
- [x] Test baru: dual verify mismatch, API timeout/retry, reconciliation scenarios
- [x] `config/payment.php` dan `.env.example` diupdate
- [x] `php artisan test` — semua pass
