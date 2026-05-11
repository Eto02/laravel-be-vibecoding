# MODULE 6 — Order Management
**Priority:** 🟠 P1 | **Status:** ✅ Selesai | **Sprint:** 6

---

## Yang Perlu Dibangun
- ⬜ Checkout (pilih alamat, kurir, apply voucher opsional)
- ⬜ Create order dari cart (kurangi stok otomatis, wrap dalam DB transaction)
- ⬜ Order status flow: `pending → paid → processing → shipped → delivered → completed`
- ⬜ Auto-cancel jika belum dibayar dalam batas waktu (Queue Job + scheduler)
- ⬜ Buyer: cancel order (jika masih `pending`), confirm received
- ⬜ Merchant: konfirmasi pesanan (`processing`), input resi (`shipped`)
- ⬜ Order Dispute (komplain)
- ⬜ Order history buyer + filter by status
- ⬜ Merchant order list + filter

---

## Entities

| Tabel | Kolom Utama |
|---|---|
| `orders` | `id`, `order_number` (unique, human-readable), `user_id`, `store_id` *(denorm)*, `address_snapshot` (JSON), `subtotal`, `shipping_fee`, `discount`, `total`, `status` (enum), `payment_due_at`, `notes`, `timestamps` |
| `order_items` | `id`, `order_id`, `product_variant_id`, `product_snapshot` (JSON), `quantity`, `unit_price`, `subtotal`, `timestamps` |
| `order_status_logs` | `id`, `order_id`, `from_status`, `to_status`, `note`, `changed_by` (user_id nullable), `timestamps` |
| `order_disputes` | `id`, `order_id`, `user_id`, `reason`, `description`, `status` (open/under_review/resolved/rejected), `resolution`, `timestamps` |

> **Catatan Desain:**
> - `orders.order_number` — format `INV/{YYYY}/{MM}/{padded_id}`, di-generate di `OrderService` setelah insert. Human-readable untuk buyer, CS, dan merchant.
> - `orders.store_id` — denormalized dari `order_items`. Satu order = satu toko. Dipakai untuk `GET /api/merchant/orders` filter tanpa join ke items.
> - `address_snapshot` — JSON snapshot dari `Address` saat checkout. Field: `recipient_name`, `phone`, `province`, `city`, `district`, `postal_code`, `street`. Tidak berubah meski user edit alamat setelahnya.
> - `product_snapshot` — JSON snapshot dari `ProductVariant` + `Product` saat checkout. Field: `product_name`, `sku`, `attributes`, `thumbnail_url`. Untuk tampilan order history walau produk sudah dihapus.
> - `order_status_logs.from_status` — penting untuk audit trail dan dispute resolution.
> - Semua monetary field: **integer cents**.

---

## Enums

```php
// app/Enums/OrderStatus.php
enum OrderStatus: string {
    case Pending    = 'pending';
    case Paid       = 'paid';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
    case Disputed   = 'disputed';
}

// app/Enums/DisputeStatus.php
enum DisputeStatus: string {
    case Open        = 'open';
    case UnderReview = 'under_review';
    case Resolved    = 'resolved';
    case Rejected    = 'rejected';
}
```

---

## Order State Machine

| From | To | Trigger |
|---|---|---|
| `pending` | `paid` | Payment Webhook (Success) via `Payment\PaymentCaptured` event |
| `pending` | `cancelled` | Expiry Job / Buyer cancel request |
| `paid` | `processing` | Merchant confirm (`PUT /merchant/orders/{id}/confirm`) |
| `paid` | `cancelled` | Merchant reject → auto-refund trigger |
| `processing` | `shipped` | Merchant input AWB (`PUT /merchant/orders/{id}/ship`) |
| `shipped` | `delivered` | Buyer confirm (`POST /orders/{id}/receive`) |
| `delivered` | `completed` | Auto-complete 3 hari via Job / Buyer confirm |
| `any` | `disputed` | Buyer komplain (`POST /orders/{id}/disputes`) |

> **Validasi transisi:** `OrderService` wajib validasi transisi sebelum update. Gunakan method `canTransitionTo(OrderStatus $new): bool` di model atau service. Throw `DomainException` jika transisi tidak valid.

---

## Routes

```
# Buyer
POST /api/orders/checkout                      [auth:sanctum]
GET  /api/orders                               [auth:sanctum]
GET  /api/orders/{id}                          [auth:sanctum]  ← IDOR: hanya order milik user
POST /api/orders/{id}/cancel                   [auth:sanctum]  ← hanya jika status pending
POST /api/orders/{id}/receive                  [auth:sanctum]  ← konfirmasi terima (shipped → delivered)
POST /api/orders/{id}/disputes                 [auth:sanctum]

# Merchant
GET  /api/merchant/orders                      [auth:sanctum, merchant]
GET  /api/merchant/orders/{id}                 [auth:sanctum, merchant]  ← IDOR: hanya order store sendiri
PUT  /api/merchant/orders/{id}/confirm         [auth:sanctum, merchant]  ← paid → processing
PUT  /api/merchant/orders/{id}/ship            [auth:sanctum, merchant]  ← processing → shipped + input AWB
```

---

## CheckoutRequest Shape

```json
{
    "items": [
        {
            "store_id": 1,
            "address_id": 3,
            "shipping_courier": "jne",
            "shipping_service": "REG",
            "shipping_fee": 15000,
            "notes": "Tolong bubble wrap"
        }
    ],
    "voucher_code": null
}
```

> **Catatan:** `items` adalah per-toko (sudah di-group dari cart). Client mengirim `shipping_fee` hasil kalkulasi di frontend. Sprint 6: fee diterima as-is tanpa re-validasi backend (ShippingService belum ada). Sprint 8: tambahkan re-kalkulasi backend + toleransi ±Rp500.
> **Voucher:** `voucher_code` opsional. Sprint 6: field diterima tapi diabaikan (VoucherService belum ada). Sprint 11: aktifkan validasi voucher.

---

## DTOs

```php
// app/DTOs/Order/CheckoutItemDTO.php
readonly class CheckoutItemDTO {
    public function __construct(
        public int     $storeId,
        public int     $addressId,
        public string  $shippingCourier,
        public string  $shippingService,
        public int     $shippingFee,
        public ?string $notes,
    ) {}
}

// app/DTOs/Order/CheckoutDTO.php
readonly class CheckoutDTO {
    public function __construct(
        public array   $items,     // CheckoutItemDTO[]
        public ?string $voucherCode,
    ) {}

    public static function fromRequest(CheckoutRequest $request): self { ... }
}
```

---

## Events

| Event | Listener | Queue |
|---|---|---|
| `Order\OrderPlaced` | `SendOrderConfirmationEmail` | ✅ ShouldQueue |
| `Order\OrderPlaced` | `NotifyMerchantNewOrder` | ✅ ShouldQueue |
| `Order\OrderCancelled` | `RestoreProductStock` | ✅ ShouldQueue |
| `Order\OrderCancelled` | `ProcessRefundIfPaid` | ✅ ShouldQueue |
| `Order\OrderShipped` | `SendShippingNotification` | ✅ ShouldQueue |
| `Order\OrderDelivered` | `SendDeliveredNotification` | ✅ ShouldQueue |

> `Payment\PaymentCaptured` (dari Sprint 7) → Listener `UpdateOrderStatusToPaid` di Sprint 7, bukan Sprint 6. Sprint 6 hanya dispatch event, Sprint 7 yang menghubungkan ke payment.

---

## Files to Create

```
# Enums
app/Enums/OrderStatus.php
app/Enums/DisputeStatus.php

# DTOs
app/DTOs/Order/CheckoutDTO.php
app/DTOs/Order/CheckoutItemDTO.php

# Models + Migrations
app/Models/Order.php
app/Models/OrderItem.php
app/Models/OrderStatusLog.php
app/Models/OrderDispute.php
database/migrations/xxxx_create_orders_table.php
database/migrations/xxxx_create_order_items_table.php
database/migrations/xxxx_create_order_status_logs_table.php
database/migrations/xxxx_create_order_disputes_table.php

# Services
app/Services/Order/OrderService.php

# Controllers + Requests + Resources
app/Http/Controllers/Api/Order/OrderController.php
app/Http/Requests/Order/CheckoutRequest.php
app/Http/Requests/Order/ShipOrderRequest.php        ← tracking_number wajib
app/Http/Requests/Order/StoreDisputeRequest.php
app/Http/Resources/Order/OrderResource.php
app/Http/Resources/Order/OrderListResource.php
app/Http/Resources/Order/OrderItemResource.php
app/Http/Resources/Order/OrderDisputeResource.php

# Events + Listeners
app/Events/Order/OrderPlaced.php
app/Events/Order/OrderCancelled.php
app/Events/Order/OrderShipped.php
app/Events/Order/OrderDelivered.php
app/Listeners/Order/SendOrderConfirmationEmail.php  (ShouldQueue)
app/Listeners/Order/NotifyMerchantNewOrder.php      (ShouldQueue)
app/Listeners/Order/RestoreProductStock.php         (ShouldQueue)
app/Listeners/Order/ProcessRefundIfPaid.php         (ShouldQueue — stub, aktif di Sprint 7)

# Jobs
app/Jobs/CancelExpiredOrderJob.php
app/Console/Commands/CancelExpiredOrdersCommand.php

# Mails
app/Mail/Order/OrderConfirmationMail.php
app/Mail/Order/OrderShippedMail.php

# Routes + Tests
routes/api/order.php
tests/Feature/Api/Order/OrderTest.php
```

---

## Shared Services Needed

| Service | Kegunaan |
|---|---|
| `EmailService` | `OrderConfirmationMail`, `OrderShippedMail` |
| `IdempotencyService` | Mencegah double checkout via `X-Idempotency-Key` header |
| `CacheService` | Invalidate cart cache setelah checkout |

---

## Business Logic Notes

### Checkout Flow (dalam satu DB Transaction)
```
1. Validate X-Idempotency-Key (IdempotencyService)
2. Load cart → groupByStore() → validasi tidak kosong
3. Untuk setiap store group:
   a. Validasi semua CartItem: product active, stock cukup (SELECT FOR UPDATE)
   b. Buat Order record (status: pending, payment_due_at: +24 jam)
   c. Buat OrderItems dari cart items (dengan product_snapshot)
   d. Snapshot address dari address_id
   e. Decrement stock: DB::table('product_variants')->where('id', $variantId)->decrement('stock', $qty)
      → Observer otomatis sync products.total_stock
   f. Log status: pending (OrderStatusLog)
   g. Generate order_number: "INV/{Y}/{m}/{str_pad($order->id, 6, '0', STR_PAD_LEFT)}"
4. Clear cart: CartService::clear($user)
5. Dispatch OrderPlaced event untuk setiap order
6. Return semua orders yang dibuat
```

### IDOR Protection
- `GET /api/orders/{id}` — query `Order::where('id', $id)->where('user_id', $user->id)->firstOrFail()`
- `GET /api/merchant/orders/{id}` — query `Order::where('id', $id)->where('store_id', $store->id)->firstOrFail()`
- Jangan pakai Policy Eloquent untuk ini — cukup scope query di `OrderService`.

### Stock Decrement
```php
// Gunakan raw decrement dengan lock untuk mencegah race condition
DB::table('product_variants')
    ->where('id', $variantId)
    ->where('stock', '>=', $qty)   // pastikan tidak negatif
    ->decrement('stock', $qty);

// Cek affected rows — jika 0 berarti stok habis saat transaksi berlangsung
if (DB::affectedRows() === 0) {
    throw new \DomainException("Stok {$productName} habis, silakan update cart.");
}
```

### Order Cancellation & Stock Restore
- Buyer hanya bisa cancel jika status `pending`
- Jika cancel setelah `paid` (misal merchant reject) → dispatch `OrderCancelled` → `ProcessRefundIfPaid` listener
- `RestoreProductStock` listener: increment stock kembali untuk setiap order_item

### Auto-cancel Job
```php
// CancelExpiredOrderJob — di-dispatch per order saat checkout
// Delay: payment_due_at - now()
// Jika order masih pending saat job run → cancel

// CancelExpiredOrdersCommand — fallback scheduler untuk yang missed
// Schedule: every 15 minutes
```

### order_number Generation
```php
// Di-generate SETELAH order di-insert (perlu ID)
$order->update([
    'order_number' => 'INV/' . now()->format('Y/m') . '/' . str_pad($order->id, 6, '0', STR_PAD_LEFT)
]);
// Contoh: INV/2026/05/000001
```

### Dependency dengan Sprint 7 & 8
| Feature | Sprint 6 | Sprint 7 | Sprint 8 |
|---|---|---|---|
| Shipping fee | Terima dari client as-is | — | Re-validasi backend |
| Voucher | Field diterima, diabaikan | — | — |
| Voucher aktif | — | — | Sprint 11 |
| Payment initiation | Order status `pending` | `POST /payments/initiate` | — |
| Order → Paid | Menunggu webhook | `PaymentCaptured` listener | — |
| AWB tracking | Simpan tracking_number | — | Tracking real-time |

---

## Test Scenarios

### OrderTest (Feature)
- `test_guest_cannot_checkout`
- `test_user_can_checkout_and_creates_order_per_store`
- `test_checkout_decrements_variant_stock`
- `test_checkout_clears_cart_after_success`
- `test_checkout_fails_if_stock_insufficient`
- `test_checkout_fails_if_cart_empty`
- `test_checkout_requires_idempotency_key`
- `test_duplicate_checkout_with_same_key_returns_cached_result`
- `test_user_can_view_own_orders`
- `test_user_cannot_view_other_users_order`
- `test_buyer_can_cancel_pending_order`
- `test_buyer_cannot_cancel_paid_order`
- `test_buyer_can_confirm_received`
- `test_merchant_can_confirm_order`
- `test_merchant_can_ship_order_with_tracking_number`
- `test_merchant_cannot_access_other_stores_order`
- `test_buyer_can_create_dispute`
- `test_cancelled_order_restores_stock`

---

## Checklist Eksekusi

- [ ] Buat Enums: `OrderStatus`, `DisputeStatus`
- [ ] Buat Migrations (4 tabel)
- [ ] Buat Models: `Order`, `OrderItem`, `OrderStatusLog`, `OrderDispute`
- [ ] Buat DTOs: `CheckoutDTO`, `CheckoutItemDTO`
- [ ] Buat `OrderService` dengan full checkout flow dalam DB Transaction
- [ ] Buat Events + Listeners (stub `ProcessRefundIfPaid` untuk Sprint 7)
- [ ] Buat `CancelExpiredOrderJob` + `CancelExpiredOrdersCommand`
- [ ] Buat Controller, FormRequests, Resources
- [ ] Register routes di `routes/api/order.php` + tambah ke `routes/api.php`
- [ ] Register events di `AppServiceProvider`
- [ ] Buat `OrderConfirmationMail` + `OrderShippedMail`
- [ ] Tambah DevSeeder: buat order sample untuk buyer
- [ ] Buat Feature Tests
- [ ] Update Postman collection (folder "07. Order")
- [ ] Update planning/06-order.md → Status ✅ Selesai
