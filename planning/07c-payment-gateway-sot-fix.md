# Sprint 07c — Payment Gateway SOT Fix

**Status:** 🔲 Belum dikerjakan
**Branch:** `fix/payment-gateway-sot-fix` (dari `main` setelah PR #29 merged)
**Affects:** Sprint 7 — Payment Module
**Predecessor:** Sprint 07b Payment SOT Hardening (`09cb994`)

---

## Problem Statement

Sprint 07b membangun arsitektur dual-verification yang solid, namun terdapat **4 bug tersembunyi** yang ditemukan saat analisis post-merge:

1. **Critical:** `handleWebhook()` memanggil `getPaymentStatus()` dengan `external_id` dari webhook payload — bukan `gateway_ref` dari DB. Untuk Xendit ini berarti kita memanggil API dengan `external_id` yang berbeda dari `invoice_id` yang disimpan saat create charge.
2. **Minor naming:** Parameter `getPaymentStatus(string $externalId)` mislabeled — di Xendit ini sebenarnya `invoice_id`/`gateway_ref`, bukan `external_id`.
3. **Logic bug:** Midtrans `capture + challenge` (fraud review in-progress) di-map ke `failed` di kedua method. Seharusnya `pending` — uang belum confirmed, tapi juga belum gagal.
4. **Amount bug:** `parseWebhookPayload()` menggunakan `str_replace` untuk parse amount, sementara `parseStatusResponse()` sudah benar dengan `* 100`. Dua path berbeda → inconsistent amount di DB.

---

## Root Cause Analysis

### Bug 1 — Wrong ID untuk Dual Verification (Critical)

```php
// PaymentService.php:103–121 — BUGGY
public function handleWebhook(Request $request, string $provider): void
{
    $normalized = $gateway->parseWebhookPayload($request);
    $externalId = $normalized['external_id'];          // ← dari webhook payload

    $payment = $this->findPaymentByExternalId($externalId);

    // BUG: memanggil API dengan external_id dari webhook,
    //      tapi Xendit API /invoices/{id} butuh invoice_id (= gateway_ref di DB)
    $apiResponse = $gateway->getPaymentStatus($externalId);  // ← WRONG
}
```

**Yang benar:** setelah `$payment` ditemukan di DB, gunakan `$payment->gateway_ref` untuk memanggil gateway API — karena `gateway_ref` adalah ID yang disimpan saat `createCharge()` dan dijamin valid untuk API call.

```php
// FIX:
$payment     = $this->findPaymentByExternalId($externalId);
$apiResponse = $gateway->getPaymentStatus($payment->gateway_ref); // ← gunakan dari DB
```

### Bug 2 — Misleading Parameter Name

```php
// PaymentGatewayInterface.php:16 — MISLEADING
public function getPaymentStatus(string $externalId): array;
//                                       ^^^^^^^^^^
// Xendit: ini sebenarnya invoice_id / gateway_ref, bukan external_id
// Midtrans: ini order_id (= external_id), tapi penamaan membingungkan
```

Rename ke `$gatewayRef` agar konsisten dengan field `payments.gateway_ref` di DB dan menghindari konfusi.

### Bug 3 — Midtrans `capture + challenge` → `failed` (Wrong)

```php
// MidtransPaymentService.php — BUGGY (di parseStatusResponse & parseWebhookPayload)
$isPaid = ($transactionStatus === 'capture' && $fraudStatus === 'accept')
    || $transactionStatus === 'settlement';

$status = match (true) {
    $isPaid                                                    => 'paid',
    in_array($transactionStatus, ['cancel', 'expire', 'deny']) => 'expired',
    $transactionStatus === 'failure'                           => 'failed',
    $transactionStatus === 'pending'                           => 'pending',
    default                                                    => 'failed',  // ← capture+challenge hits here
};
```

Midtrans `capture + challenge` = transaksi di-capture tapi sedang dalam fraud review. Status ini **bukan gagal** — Midtrans sedang memverifikasi. Jika di-map ke `failed` → order di-cancel padahal uang sudah ditahan gateway.

**Yang benar:** `capture + challenge` → `pending` (tunggu resolusi fraud review dari Midtrans).

### Bug 4 — Inconsistent Amount Parsing

```php
// parseStatusResponse() — BENAR
'amount' => (int) round((float) ($apiResponse['gross_amount'] ?? 0) * 100),
// "100000.00" → 10000000 ✅

// parseWebhookPayload() — BUGGY
'amount' => (int) str_replace([',', '.00'], '', $payload['gross_amount'] ?? '0'),
// "100000.00" → str_replace → "100000" → 100000 ✗ (harusnya 10000000 cents)
// "1,500,000.00" → str_replace → "1500000" → 1500000 ✗ (harusnya 150000000 cents)
```

Dua path (webhook vs API poll) menghasilkan amount yang berbeda di DB — bug saat reconciliation.

---

## Scope Perbaikan

### File yang Diubah

| File | Perubahan |
|---|---|
| `app/Services/Payment/PaymentGatewayInterface.php` | Rename param `$externalId` → `$gatewayRef` di `getPaymentStatus()` |
| `app/Services/Payment/XenditPaymentService.php` | Rename param di implementasi |
| `app/Services/Payment/MidtransPaymentService.php` | Rename param + fix `capture+challenge` + fix amount parsing |
| `app/Services/Payment/PaymentService.php` | Fix `handleWebhook()`: gunakan `$payment->gateway_ref` untuk API call |
| `tests/Unit/Services/Payment/MidtransPaymentServiceTest.php` | Tambah test case `capture+challenge → pending` + amount parsing |
| `tests/Unit/Services/Payment/PaymentServiceTest.php` | Update test untuk verifikasi `gateway_ref` dipakai saat dual-verify |

### Yang TIDAK diubah

- Logika `applyStatusTransition()`, `findPaymentByExternalId()`, `markPaid()`, `markExpired()` — sudah benar dari 07b
- `ReconcilePayments` job — sudah benar, gunakan `handleApiStatusUpdate()` bukan `handleWebhook()`
- `ProcessWebhookJob` — tidak diubah, hanya fix di service yang dipanggil

---

## Implementation Plan

### Phase 1 — Interface & Naming (tidak ada perubahan behavior)

**`PaymentGatewayInterface.php`**
```php
// Sebelum:
public function getPaymentStatus(string $externalId): array;

// Sesudah:
public function getPaymentStatus(string $gatewayRef): array;
```

**`XenditPaymentService.php`**
```php
// Sebelum:
public function getPaymentStatus(string $externalId): array
{
    $response = Http::withToken($this->secretKey)
        ->get("https://api.xendit.co/v2/invoices/{$externalId}");
    ...
}

// Sesudah:
public function getPaymentStatus(string $gatewayRef): array
{
    $response = Http::withToken($this->secretKey)
        ->get("https://api.xendit.co/v2/invoices/{$gatewayRef}");
    ...
}
```

**`MidtransPaymentService.php`**
```php
// Sebelum:
public function getPaymentStatus(string $externalId): array

// Sesudah:
public function getPaymentStatus(string $gatewayRef): array
```

### Phase 2 — Fix Critical Bug: handleWebhook() (PaymentService)

```php
public function handleWebhook(Request $request, string $provider): void
{
    $gateway    = app("payment.{$provider}");
    $normalized = $gateway->parseWebhookPayload($request);
    $externalId = $normalized['external_id'];

    $payment = $this->findPaymentByExternalId($externalId);
    if (! $payment) {
        return;
    }

    // FIX: gunakan gateway_ref dari DB, bukan external_id dari webhook
    $apiResponse = $gateway->getPaymentStatus($payment->gateway_ref);
    $verified    = $gateway->parseStatusResponse($apiResponse);

    $this->applyStatusTransition($payment, $verified['status'], $verified['amount']);
}
```

### Phase 3 — Fix Midtrans Logic Bugs

**`parseStatusResponse()`** — tambah arm untuk `capture + challenge`:
```php
$isPaid = ($transactionStatus === 'capture' && $fraudStatus === 'accept')
    || $transactionStatus === 'settlement';

$isFraudReview = $transactionStatus === 'capture' && $fraudStatus === 'challenge';

$status = match (true) {
    $isPaid                                                    => 'paid',
    $isFraudReview                                             => 'pending', // fraud review, bukan gagal
    in_array($transactionStatus, ['cancel', 'expire', 'deny']) => 'expired',
    $transactionStatus === 'failure'                           => 'failed',
    $transactionStatus === 'pending'                           => 'pending',
    default                                                    => 'failed',
};
```

**`parseWebhookPayload()`** — fix amount + tambah `deny` + `capture+challenge`:
```php
$isFraudReview = $transactionStatus === 'capture' && $fraudStatus === 'challenge';

$status = match (true) {
    $isPaid                                                    => 'paid',
    $isFraudReview                                             => 'pending',
    in_array($transactionStatus, ['cancel', 'expire', 'deny']) => 'expired',
    $transactionStatus === 'pending'                           => 'pending',
    default                                                    => 'failed',
};

return [
    'event'       => $status === 'paid' ? 'payment.succeeded' : 'payment.' . $status,
    'external_id' => $payload['order_id'] ?? '',
    'status'      => $status,
    // FIX: konsisten dengan parseStatusResponse — gunakan * 100
    'amount'      => (int) round((float) ($payload['gross_amount'] ?? 0) * 100),
];
```

> **Catatan:** `parseWebhookPayload()` sebelumnya juga tidak ada arm untuk `deny` — sekarang ditambahkan sekalian untuk konsistensi dengan `parseStatusResponse()`.

### Phase 4 — Unit Tests

**`MidtransPaymentServiceTest`** — tambah cases:
```
[parseStatusResponse]
- capture + accept → paid ✅ (existing)
- settlement → paid ✅ (existing)
- capture + challenge → pending (NEW)
- cancel → expired ✅ (existing)
- deny → expired (verify, was in default=failed before)
- amount "100000.00" → 10000000 cents (NEW verify)
- amount "1,500,000.00" → 150000000 cents (NEW verify)

[parseWebhookPayload]
- capture + accept → paid, amount correct (NEW verify)
- capture + challenge → pending (NEW)
- deny → expired (verify)
- amount "100000.00" → 10000000 cents (NEW)
```

**`PaymentServiceTest`** — tambah case:
```
- handleWebhook() uses payment->gateway_ref for getPaymentStatus(), not external_id from webhook
```

---

## Risk Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| Bug 1 fix: jika `gateway_ref` null (payment lama tanpa gateway_ref) | Medium | `handleApiStatusUpdate()` sudah handle ini dengan fallback ke `transaction->external_id`. Untuk `handleWebhook()`, jika `gateway_ref` null → log warning + skip dual-verify → masih aman karena `applyStatusTransition()` memiliki guard |
| Midtrans `deny` sudah ada di `parseStatusResponse` tapi tidak di `parseWebhookPayload` | Low | Ditambahkan sekalian di Phase 3, tidak ada behavioral regression karena sebelumnya `deny` jatuh ke `default => failed` di kedua tempat |
| Rename parameter: breaking change? | None | PHP tidak punya named arguments untuk interface methods secara wajib. Rename parameter tidak breaking selama signature type-nya sama |

---

## Definition of Done

- [ ] `getPaymentStatus(string $gatewayRef)` — renamed di interface + 2 implementasi
- [ ] `handleWebhook()` menggunakan `$payment->gateway_ref` untuk API call
- [ ] Midtrans `capture + challenge` → `pending` di `parseStatusResponse()` dan `parseWebhookPayload()`
- [ ] Midtrans `deny` → `expired` di `parseWebhookPayload()` (sebelumnya hanya di `parseStatusResponse`)
- [ ] `parseWebhookPayload()` amount: `str_replace` → `* 100`
- [ ] Unit tests: semua case di atas covered
- [ ] `php artisan test` pass
- [ ] `api-collections/` tidak perlu diupdate (tidak ada endpoint baru)
- [ ] Self-review report dikirim
