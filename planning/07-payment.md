# MODULE 7 — Payment
**Priority:** 🟠 P1 | **Status:** ✅ Selesai | **Sprint:** 7

---

## Yang Sudah Ada ✅
- Xendit Invoice creation (`XenditPaymentService::createPaymentIntent()`)
- Webhook handler (`WebhookController::xendit()`)
- `Transaction` model + `TransactionStatus` enum
- `PaymentGatewayInterface` (4 methods — akan di-expand)
- `PaymentController::store()` (basic)
- `PaymentCaptured` event (ada, belum di-dispatch)

## Yang Perlu Dibangun ✅
- ✅ Expand `PaymentGatewayInterface` → unified `createCharge()` + `parseWebhookPayload()`
- ✅ `XenditPaymentService`: Invoice only (Xendit hosted checkout — VA/QRIS/e-wallet handled by Xendit UI)
- ✅ `MidtransPaymentService`: Snap API (hosted checkout, semua method via satu endpoint)
- ✅ `PaymentService`: method routing, expiry check, refund logic
- ✅ `WalletService`: top-up, credit, debit, withdraw (merchant payout)
- ✅ `Payment` model + migration
- ✅ `Refund` model + migration
- ✅ `WalletBalance` + `WalletTransaction` models + migrations
- ✅ `PaymentStatus`, `RefundStatus` enums
- ✅ Events: `PaymentFailed`, `RefundProcessed` (wire `PaymentCaptured`)
- ✅ Listeners: `UpdateOrderStatus`, `CreditMerchantWallet` (on `OrderCompleted`), `SendPaymentSuccessMail`, `NotifyPaymentFailed`, `CreditUserWallet`
- ✅ `PaymentSuccessMail` mailable
- ✅ `ExpireUnpaidPayments` Job + scheduler (every 5 min)
- ✅ `config/platform.php` (fee %, pending Sprint 12 DB migration)

---

## Architecture: Two-Layer Payment Design

```
Order → Payment (domain record) → Transaction (gateway log)
```

- **`Transaction`** — raw gateway record: external ID, invoice URL, gateway timestamps. Read-only setelah dibuat. Satu per gateway call.
- **`Payment`** — domain entity utama: link `order_id → transaction_id`, simpan `method`, `gateway`, `status`, `payment_details`. Ini yang di-reason oleh aplikasi.

Webhook masuk → `WebhookController` → `PaymentService::handleWebhook()` → update `Payment` status → dispatch `PaymentCaptured` atau `PaymentFailed`.

---

## Entities

| Tabel | Kolom |
|---|---|
| `transactions` | (existing) `id`, `external_id`, `status` (`TransactionStatus`), `amount`, `invoice_url`, `paid_at`, `timestamps` |
| `payments` | `id`, `order_id` (FK), `transaction_id` (FK nullable), `gateway` (xendit/midtrans/wallet), `method` (invoice/virtual_account/qris/ewallet/snap/wallet), `gateway_ref`, `amount` (integer cents), `status` (`PaymentStatus`), `payment_details` (JSON), `expires_at`, `timestamps` |
| `refunds` | `id`, `payment_id` (FK), `amount` (integer cents), `reason`, `status` (`RefundStatus`), `gateway_ref`, `refunded_at`, `timestamps` |
| `wallet_balances` | `id`, `user_id` (FK unique), `balance` (integer cents), `on_hold` (integer cents), `timestamps` |
| `wallet_transactions` | `id`, `wallet_balance_id` (FK), `type` (credit/debit), `amount` (integer cents), `description`, `reference_type`, `reference_id`, `timestamps` |

**Index wajib:**
- `payments`: `order_id`, `status`, `expires_at`, `gateway_ref` (unique)
- `refunds`: `payment_id`, `status`
- `wallet_balances`: `user_id` (unique)
- `wallet_transactions`: `wallet_balance_id`, `type`

---

## Enums

```php
enum PaymentStatus: string {
    case Pending  = 'pending';
    case Paid     = 'paid';
    case Failed   = 'failed';
    case Expired  = 'expired';
    case Refunded = 'refunded';
}

// TransactionStatus tetap ada — dipakai Transaction model (gateway log only)
enum TransactionStatus: string {
    case Pending = 'pending';
    case Paid    = 'paid';
    case Expired = 'expired';
}

enum RefundStatus: string {
    case Pending   = 'pending';
    case Processed = 'processed';
    case Rejected  = 'rejected';
}
```

---

## Routes

```
POST /api/payments/initiate                    [auth] — buat Payment + gateway charge
GET  /api/payments/{id}/status                 [auth] — poll status payment (ownership-checked)
POST /api/payments/{id}/switch                 [auth] — ganti metode pembayaran (cancel old + create new)
POST /api/payments/{id}/refund                 [auth] — request refund

POST /api/webhooks/{provider}                  [public, signature-verified]

GET  /api/wallet/balance                       [auth]
GET  /api/wallet/transactions                  [auth]
POST /api/wallet/topup                         [auth]
POST /api/wallet/withdraw                      [auth:merchant]
```

**`POST /api/payments/initiate` request body:**
```json
{
  "order_id": 1,
  "gateway": "xendit"
}
```

Method diassign otomatis: `xendit` → `invoice`, `midtrans` → `snap`.

---

## Interface Contract (revised — unified `createCharge`)

```php
interface PaymentGatewayInterface {
    /**
     * Unified charge entry point. $data includes: external_id, amount, method,
     * bank_code (VA), ewallet_type + phone (e-wallet), success_redirect_url.
     * Returns: gateway_ref, redirect_url|null, payment_details, expires_at.
     */
    public function createCharge(array $data): array;

    /**
     * Cancel / void a pending charge before it is paid.
     * $method needed because Xendit has different endpoints per method.
     * Best-effort: if gateway already expired the charge, return true silently.
     */
    public function cancelCharge(string $chargeRef, string $method): bool;

    public function getPaymentStatus(string $externalId): array;

    public function refundPayment(string $chargeRef, int $amount): array;

    public function verifyWebhook(Request $request): bool;

    /**
     * Normalize gateway webhook payload to standard format:
     * ['event' => string, 'external_id' => string, 'status' => string, 'amount' => int]
     * status values: 'paid' | 'failed' | 'expired' | 'pending' (no-op)
     */
    public function parseWebhookPayload(Request $request): array;
}
```

---

## Xendit Payment Methods — Implementation Detail

### Invoice (Hosted Checkout) ✅ — Implemented
- **API:** `POST /v2/invoices`
- **Method assigned:** `invoice` (auto, no request param needed)
- **User flow:** user diarahkan ke `redirect_url` (Xendit hosted page) → pilih metode pembayaran (VA, QRIS, e-wallet, kartu kredit, dll) → Xendit callback webhook ke Laravel
- **Webhook event:** `status: PAID | EXPIRED`, header `X-CALLBACK-TOKEN`
- **`payment_details` JSON:** `{ "external_id": "PAY-xxx", "invoice_id": "xxx", "invoice_url": "https://checkout.xendit.co/..." }`
- **Keuntungan:** satu integrasi mendukung semua metode — tidak perlu per-method implementation

### Sandbox Simulation
- `POST https://api.xendit.co/v2/invoices/{invoice_id}/simulate_payment` — trigger paid webhook
- Gunakan `invoice_id` dari `payment_details.invoice_id`

---

## Midtrans Snap API — Implementation Detail

### Flow
1. `PaymentService::initiatePayment()` → gateway `createCharge()` → Snap creates transaction
2. Midtrans returns `snap_token` + `redirect_url` (Snap hosted checkout page)
3. Client redirect ke Snap page — user pilih method (VA, QRIS, GoPay, ShopeePay, dll)
4. Midtrans POST ke `POST /api/webhooks/midtrans` setelah pembayaran

### Auth
- Basic auth: Server Key sebagai username, password kosong
- Endpoint: `https://app.sandbox.midtrans.com/snap/v1/transactions` (sandbox)

### createCharge() request body
```json
{
  "transaction_details": { "order_id": "ORDER-xxx", "gross_amount": 100000 },
  "customer_details": { "first_name": "...", "email": "...", "phone": "..." },
  "callbacks": {
    "finish": "{{APP_URL}}/payment/finish"
  }
}
```
- Returns: `{ "token": "...", "redirect_url": "https://app.sandbox.midtrans.com/snap/v3/redirection/..." }`

### Webhook Verification
```php
// Midtrans webhook signature (SHA-512):
$expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
// Bandingkan dengan $payload['signature_key']
```

### Webhook Status Mapping
| `transaction_status` | `fraud_status` | Payment Status |
|---|---|---|
| `capture` | `accept` | `paid` |
| `settlement` | — | `paid` |
| `deny` | — | `failed` |
| `cancel` / `expire` | — | `expired` / `failed` |

### Config
```
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SNAP_URL=https://app.sandbox.midtrans.com/snap/v1/transactions
```

### `payment_details` JSON untuk Snap
```json
{ "snap_token": "xxx", "redirect_url": "https://app.sandbox.midtrans.com/snap/..." }
```

---

## Events

| Event | Listener(s) | Keterangan |
|---|---|---|
| `Payment\PaymentCaptured` | `UpdateOrderStatus`, `SendPaymentSuccessMail` | Order `pending → paid`. Wire event yang sudah ada. |
| `Payment\PaymentFailed` | `NotifyPaymentFailed` | Log + notifikasi buyer |
| `Payment\RefundProcessed` | `CreditUserWallet`, `SendRefundNotification` | Credit wallet buyer atau konfirmasi bank refund |
| `Order\OrderCompleted` *(Sprint 6)* | **`CreditMerchantWallet`** ← tambah listener | Potong platform fee, kredit merchant wallet |

> ⚠️ `CreditMerchantWallet` listener on `OrderCompleted`, **bukan** `PaymentCaptured`. Escrow model — merchant hanya dibayar setelah order selesai.

---

## Platform Fee

```php
// config/platform.php
return [
    'fee_percent' => (float) env('PLATFORM_FEE_PERCENT', 5.0), // 5% default
];
```

Dipakai di `WalletService::creditMerchant()`:
```php
$fee = (int) round($orderAmount * config('platform.fee_percent') / 100);
$merchantCredit = $orderAmount - $fee;
```

> **Sprint 12 TODO:** Switch dari `config('platform.fee_percent')` ke `PlatformSetting::get('fee_percent')` setelah Admin panel buat tabel `platform_settings`. `WalletService` adalah satu-satunya file yang perlu diubah.

---

## `on_hold` Balance Lifecycle (Merchant Wallet)

| Trigger | Action |
|---|---|
| Merchant request `POST /wallet/withdraw` | `balance -= amount`, `on_hold += amount` |
| Disbursement webhook SUCCESS | `on_hold -= amount` (permanently deducted) |
| Disbursement webhook FAILED | `on_hold -= amount`, `balance += amount` (rollback) |

---

## Shared Services

| Service | Kegunaan |
|---|---|
| `EmailService` | `PaymentSuccessMail` via `SendPaymentSuccessMail` listener |
| `IdempotencyService` | Validasi `X-Idempotency-Key` pada `POST /payments/initiate` |

---

## Files to Create/Update (~40 files)

```
config/platform.php                                               (new)

database/migrations/xxxx_create_payments_table.php               (new)
database/migrations/xxxx_create_refunds_table.php                (new)
database/migrations/xxxx_create_wallet_balances_table.php        (new)
database/migrations/xxxx_create_wallet_transactions_table.php    (new)

app/Enums/PaymentStatus.php                                      (new)
app/Enums/RefundStatus.php                                       (new)

app/Models/Payment.php                                           (new)
app/Models/Refund.php                                            (new)
app/Models/WalletBalance.php                                     (new)
app/Models/WalletTransaction.php                                 (new)

app/Services/Payment/PaymentGatewayInterface.php                 (update — unified createCharge)
app/Services/Payment/XenditPaymentService.php                    (update — VA, QRIS, e-wallet, parseWebhookPayload)
app/Services/Payment/MidtransPaymentService.php                  (new — Snap API full implementation)
app/Services/Payment/PaymentService.php                          (update — routing, expiry, refund, webhook)
app/Services/Payment/WalletService.php                           (new)

app/DTOs/Payment/InitiatePaymentDTO.php                          (new — >2 params)

app/Http/Requests/Payment/InitiatePaymentRequest.php             (new — replaces StorePaymentRequest)
app/Http/Requests/Payment/RefundPaymentRequest.php               (new)
app/Http/Requests/Payment/WalletTopupRequest.php                 (new)
app/Http/Requests/Payment/WalletWithdrawRequest.php              (new)

app/Http/Resources/Payment/PaymentResource.php                   (new)
app/Http/Resources/Payment/RefundResource.php                    (new)
app/Http/Resources/Payment/WalletBalanceResource.php             (new)
app/Http/Resources/Payment/WalletTransactionResource.php         (new)

app/Http/Controllers/Api/Payment/PaymentController.php           (update — initiate, status, refund)
app/Http/Controllers/Api/Payment/WalletController.php            (new)
app/Http/Controllers/Api/Payment/WebhookController.php           (update — unified {provider} dispatch)

app/Events/Payment/PaymentFailed.php                             (new)
app/Events/Payment/RefundProcessed.php                           (new)

app/Listeners/Payment/UpdateOrderStatus.php                      (new)
app/Listeners/Payment/CreditMerchantWallet.php                   (new — listens to OrderCompleted)
app/Listeners/Payment/SendPaymentSuccessMail.php                 (new)
app/Listeners/Payment/NotifyPaymentFailed.php                    (new)
app/Listeners/Payment/CreditUserWallet.php                       (new)

app/Mail/Payment/PaymentSuccessMail.php                          (new — implements ShouldQueue)

app/Jobs/ExpireUnpaidPayments.php                                (new — runs every 5 min via scheduler)

routes/api/payment.php                                           (update)

tests/Feature/Api/Payment/PaymentTest.php                        (update)
tests/Feature/Api/Payment/WalletTest.php                         (new)
tests/Feature/Api/Payment/WebhookTest.php                        (update — xendit + midtrans)

postman/07-payment.postman_collection.json                       (update)
postman/07-webhooks.postman_collection.json                      (update)
```

---

## Business Logic Notes

- Semua monetary: integer cents
- Webhook idempotency: cek `payments.gateway_ref` sudah ada di DB sebelum proses ulang
- Refund flow: `payment.status = paid` → gateway `refundPayment()`; `pending/expired` → update status saja
- `WalletBalance` dibuat on-demand (`firstOrCreate`) saat pertama kali dibutuhkan
- `ExpireUnpaidPayments` job: cari `payments` dengan `status = pending` dan `expires_at < now()` → update ke `expired` → dispatch `PaymentFailed`
- OVO e-wallet: `redirect_url` dalam response adalah `null` — frontend tahu tidak perlu redirect, cukup polling status

---

## AppServiceProvider — Gateway Binding (Sprint 7)

```php
// register() — named binding untuk kedua gateway
$this->app->bind('payment.xendit', XenditPaymentService::class);
$this->app->bind('payment.midtrans', MidtransPaymentService::class);

// Primary gateway via env — Sprint 12 akan ganti ini dengan GatewayResolver
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    $gateway = config('payment.default_gateway', 'xendit');
    return $app->make("payment.{$gateway}");
});
```

Named binding `payment.xendit` / `payment.midtrans` sengaja diekspos agar Sprint 12 bisa langsung resolve keduanya tanpa ubah implementasi.

---

## Pending Dependencies

| Sprint | Dependency |
|---|---|
| Sprint 12 (Admin) | Ganti `AppServiceProvider` binding dari env-static ke `GatewayResolver` (Tier 2) + `CircuitBreakerGatewayResolver` (Tier 3). `PaymentService` tidak perlu diubah — hanya binding. |
| Sprint 12 (Admin) | `WalletService::creditMerchant()` switch dari `config('platform.fee_percent')` ke `platform_settings` DB. |
| Sprint 8 (Shipping) | `OrderCompleted` event dari auto-complete (3 hari setelah delivered) — `CreditMerchantWallet` otomatis terpanggil. |

---

## Checklist

- [x] Migrations buat + migrate
- [x] Models + factories
- [x] Enums
- [x] PaymentGatewayInterface updated
- [x] XenditPaymentService: Invoice-only + parseWebhookPayload (simplified from VA/QRIS/e-wallet)
- [x] MidtransPaymentService: Snap API + webhook verification
- [x] PaymentService: routing + expiry + refund
- [x] WalletService
- [x] Controllers (Payment, Wallet, Webhook)
- [x] Requests + Resources + DTO
- [x] Events + Listeners
- [x] PaymentSuccessMail
- [x] ExpireUnpaidPayments job + scheduler
- [x] config/platform.php
- [x] Routes updated (+ switch endpoint)
- [x] Tests: PaymentTest + WalletTest + WebhookTest (258 pass total)
- [x] Postman updated + merge.py run
- [x] DevSeeder updated (wallet balance untuk test user)

## Post-Sprint Fixes (Session 2)

- [x] `cancelCharge()` ditambah ke interface + Xendit (VA/ewallet/QRIS/invoice) + Midtrans (best-effort)
- [x] `PaymentService::switchPayment()` — cancel gateway lama + initiate baru
- [x] `PaymentService::cancelPendingPaymentsForOrder()` — digunakan saat order dibatalkan buyer
- [x] `OrderService::cancelByBuyer()` memanggil `cancelPendingPaymentsForOrder()` sebelum cancel order
- [x] `ProcessRefundIfPaid` listener: implementasi aktual (replace stub) via `PaymentService::requestRefund()`
- [x] `PaymentController::status()` — tambah ownership check (was queryable by any authenticated user)
- [x] Fix idempotency key: wrap full `createPayment()` dalam idempotency check (bukan null-caching lookup)
- [x] `handleWebhook()` dan `requestRefund()` resolve gateway by provider/`payment.gateway` (fix multi-gateway routing)
- [x] Midtrans `pending` status webhook → no-op (sebelumnya: mapped ke failed)
- [x] Midtrans `payment_details` ditambah `external_id`
- [x] 5 test baru: switch (4) + status ownership (1)

## Post-Sprint Fixes (Session 3) — Invoice-Only Simplification

- [x] `XenditPaymentService` disederhanakan ke invoice-only (`createCharge` → `createInvoice` only)
- [x] Removed VA, QRIS, e-wallet specific charge methods from Xendit service
- [x] `InitiatePaymentRequest` simplified: hanya `order_id` + `gateway` (tidak ada `method`, `bank_code`, `ewallet_type`, `phone`)
- [x] `SwitchPaymentRequest` simplified: hanya `gateway` (tidak ada `method`)
- [x] `InitiatePaymentDTO` simplified: hapus `method`, `bankCode`, `ewalletType`, `phone`, `successRedirectUrl`
- [x] `PaymentService::createPayment()` auto-assign method: `xendit` → `invoice`, `midtrans` → `snap`
- [x] `PaymentTest` rewritten: invoice-focused, remove VA/QRIS/ewallet tests
- [x] `WebhookTest` remove `test_xendit_qris_webhook_marks_payment_paid` (QRIS removed)
- [x] Postman `07-payment.postman_collection.json` updated: invoice-only examples, sandbox simulator uses invoice simulation endpoint
- [x] DevSeeder pending order changed to cheaper product (Rp 178.000) untuk testing invoice
- [x] All 259 tests pass
