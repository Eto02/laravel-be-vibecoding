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
- ✅ `XenditPaymentService`: VA (Closed), QRIS (Dynamic), E-wallet (OVO/GoPay/Dana/ShopeePay/LinkAja)
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
GET  /api/payments/{id}/status                 [auth] — poll status payment
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
  "gateway": "xendit",
  "method": "virtual_account",
  "bank_code": "BCA",
  "ewallet_type": "GOPAY",
  "phone": "08123456789"
}
```

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

    public function getPaymentStatus(string $externalId): array;

    public function refundPayment(string $chargeRef, int $amount): array;

    public function verifyWebhook(Request $request): bool;

    /**
     * Normalize gateway webhook payload to standard format:
     * ['event' => string, 'external_id' => string, 'status' => string, 'amount' => int]
     * status values: 'paid' | 'failed' | 'expired'
     */
    public function parseWebhookPayload(Request $request): array;
}
```

---

## Xendit Payment Methods — Implementation Detail

### Virtual Account (Closed VA)
- **API:** `POST /callback_virtual_accounts` dengan `is_closed: true`
- **Banks:** BCA, BNI, BRI, MANDIRI, PERMATA, BSI, SAHABAT_SAMPOERNA
- **Expiry:** ikut `payment_due_at` dari Order (maks 72h untuk VA)
- **Webhook event:** `virtual_account.paid`, header `X-CALLBACK-TOKEN`
- **`payment_details` JSON:** `{ "bank_code": "BCA", "account_number": "8808xxx", "virtual_account_id": "xxx" }`
- **Biaya:** VA berbeda per bank — tampilkan `bank_code` ke frontend agar user tahu

### QRIS (Dynamic QR)
- **API:** `POST /qr_codes` dengan `type: DYNAMIC`
- **TTL:** 300s untuk online checkout (update `expires_at` di `payments`)
- **Webhook event:** `qr_code.payment.succeeded`
- **`payment_details` JSON:** `{ "qr_id": "xxx", "qr_string": "00020101..." }`
- **Note:** QRIS diterima semua e-wallet + mobile banking yang support QRIS (GoPay, OVO, Dana, ShopeePay, dll)

### E-wallet
- **API:** `POST /ewallets/charges`
- **Flow per type:**

| Type | Flow | `checkout_url` | Kebutuhan Extra |
|---|---|---|---|
| `OVO` | Push notif ke OVO app | ❌ | `phone` wajib |
| `DANA` | Redirect ke Dana checkout | ✅ | — |
| `GOPAY` | Deeplink / web redirect | ✅ | — |
| `SHOPEEPAY` | Redirect ke ShopeePay | ✅ | — |
| `LINKAJA` | Redirect | ✅ | — |

- **Webhook event:** `ewallet.payment`, cek `charge_status: SUCCEEDED`
- **`payment_details` JSON:** `{ "ewallet_type": "GOPAY", "charge_id": "xxx", "checkout_url": "https://..." }`
- **Note:** OVO push tidak punya `checkout_url` — response ke client berisi `null` untuk `redirect_url`

### `createCharge()` internal routing (XenditPaymentService)
```
method = virtual_account → POST /callback_virtual_accounts (is_closed: true)
method = qris           → POST /qr_codes (type: DYNAMIC)
method = ewallet        → POST /ewallets/charges
method = invoice        → POST /v2/invoices (hosted, backward-compatible)
```

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
- [x] XenditPaymentService: VA + QRIS + e-wallet + parseWebhookPayload
- [x] MidtransPaymentService: Snap API + webhook verification
- [x] PaymentService: routing + expiry + refund
- [x] WalletService
- [x] Controllers (Payment, Wallet, Webhook)
- [x] Requests + Resources + DTO
- [x] Events + Listeners
- [x] PaymentSuccessMail
- [x] ExpireUnpaidPayments job + scheduler
- [x] config/platform.php
- [x] Routes updated
- [x] Tests: PaymentTest + WalletTest + WebhookTest
- [x] Postman updated + merge.py run
- [x] DevSeeder updated (wallet balance untuk test user)
