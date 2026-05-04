# MODULE 7 — Payment
**Priority:** 🟠 P1 | **Status:** 🟡 Partial | **Sprint:** 7

---

## Yang Sudah Ada ✅
- Xendit Invoice creation (`XenditPaymentService`)
- Webhook handler (`WebhookController`)
- `Transaction` model + `TransactionStatus` enum
- `PaymentGatewayInterface`

## Yang Perlu Dibangun ⬜
- ⬜ Multiple payment methods (VA bank, QRIS, e-wallet OVO/Dana/GoPay)
- ⬜ Payment expiry auto-cancel (Job + scheduler)
- ⬜ Midtrans integration (implementasi interface yang sudah ada)
- ⬜ Digital Wallet / Saldo internal
- ⬜ Wallet top-up via payment gateway
- ⬜ Refund ke wallet saldo atau rekening asal
- ⬜ Payment receipt (kirim via email)
- ⬜ Payout ke merchant (transfer komisi)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `transactions` | Sudah ada: `external_id`, `status`, `amount`, `paid_at` |
| `payments` | `order_id`, `transaction_id`, `method` (xendit_va/qris/ewallet), `gateway`, `gateway_ref`, `amount`, `status` |
| `refunds` | `payment_id`, `amount`, `reason`, `status`, `refunded_at`, `gateway_ref` |
| `wallet_balances` | `user_id`, `balance` (integer cents), `on_hold` (integer cents) |
| `wallet_transactions` | `wallet_id`, `type` (credit/debit), `amount`, `description`, `reference_type`, `reference_id` |

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
```

---

## Routes
```
POST /api/payments/initiate                    [auth]
GET  /api/payments/{id}/status                 [auth]
POST /api/payments/{id}/refund                 [auth]

POST /api/webhooks/{provider}                  [public, signature-verified]

GET  /api/wallet/balance                       [auth]
GET  /api/wallet/transactions                  [auth]
POST /api/wallet/topup                         [auth]
POST /api/wallet/withdraw                      [auth:merchant]
```

---

## Interface Contract
```php
interface PaymentGatewayInterface {
    public function createInvoice(array $data): array;
    public function createVirtualAccount(array $data): array;
    public function createQris(array $data): array;
    public function capturePayment(string $paymentId): array;
    public function refundPayment(string $paymentId, int $amount): array;
    public function verifyWebhook(Request $request): bool;
    public function getPaymentStatus(string $paymentId): array;
}
```

---

## Events
| Event | Listener |
|---|---|
| `Payment\PaymentCaptured` | `UpdateOrderStatus`, `CreditMerchantWallet`, `SendPaymentSuccessMail` |
| `Payment\PaymentFailed` | `NotifyPaymentFailed` |
| `Payment\RefundProcessed` | `CreditUserWallet`, `SendRefundNotification` |

---

## Files to Create/Update
```
app/Services/Payment/PaymentService.php          (update)
app/Services/Payment/WalletService.php           (new)
app/Services/Payment/Gateways/XenditPaymentService.php  (update)
app/Services/Payment/Gateways/MidtransPaymentService.php (new)
app/Http/Controllers/Api/Payment/PaymentController.php  (new)
app/Http/Controllers/Api/Payment/WalletController.php   (new)
app/Http/Controllers/Api/Payment/WebhookController.php  (update)
app/Http/Resources/Payment/PaymentResource.php
app/Http/Resources/Payment/WalletResource.php
app/Models/Payment.php
app/Models/Refund.php
app/Models/WalletBalance.php
app/Models/WalletTransaction.php
app/Enums/PaymentStatus.php
app/Events/Payment/PaymentCaptured.php
app/Listeners/Payment/UpdateOrderStatus.php
app/Mail/Payment/PaymentSuccessMail.php
routes/api/payment.php
tests/Feature/Api/Payment/PaymentTest.php        (update)
tests/Feature/Api/Payment/WalletTest.php
tests/Feature/Api/Payment/WebhookTest.php        (update)
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `EmailService` | `PaymentSuccessMail`, payment receipt |
| `NotificationService` | Push notif pembayaran sukses/gagal |

---

## Business Logic Notes
- Wallet balance disimpan dalam **integer cents**
- Payout merchant: setelah order `completed`, transfer komisi ke merchant wallet
- Platform fee: dikurangi dari total sebelum kredit ke merchant (konfigurasi di platform settings)
- Refund: jika payment via gateway → refund via gateway; jika belum bayar → cancel saja
- Webhook harus idempotent — cek `external_id` sudah diproses sebelumnya
