# MODULE 6 — Order Management
**Priority:** 🟠 P1 | **Status:** ⬜ Belum | **Sprint:** 6

---

## Yang Perlu Dibangun
- ⬜ Checkout (pilih alamat, kurir, payment method, apply voucher)
- ⬜ Create order dari cart (kurangi stok otomatis, wrap dalam DB transaction)
- ⬜ Order status flow: `pending → paid → processing → shipped → delivered → completed`
- ⬜ Auto-cancel jika belum dibayar dalam batas waktu (Queue Job + scheduler)
- ⬜ Buyer: cancel order (jika masih `pending` atau `paid`)
- ⬜ Merchant: konfirmasi pesanan (`processing`), input resi (`shipped`)
- ⬜ Order Dispute (komplain)
- ⬜ Order history buyer + filter by status
- ⬜ Merchant order list + filter

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `orders` | `user_id`, `store_id`, `address_snapshot` (JSON), `subtotal`, `shipping_fee`, `discount`, `total`, `status` (enum), `payment_due_at`, `notes` |
| `order_items` | `order_id`, `product_variant_id`, `product_snapshot` (JSON), `quantity`, `unit_price`, `subtotal` |
| `order_status_logs` | `order_id`, `status`, `note`, `changed_by` (user_id) |
| `order_disputes` | `order_id`, `user_id`, `reason`, `description`, `status`, `resolution` |

---

## Enums
```php
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
```

---

## Order State Machine

| From | To | Trigger |
|---|---|---|
| `pending` | `paid` | Payment Webhook (Success) |
| `pending` | `cancelled` | Expiry Job / User Cancel |
| `paid` | `processing` | Merchant Accept |
| `paid` | `cancelled` | Merchant Reject (Auto-refund) |
| `processing` | `shipped` | Merchant Input AWB |
| `shipped` | `delivered` | Courier API / User Confirm |
| `delivered` | `completed` | Auto-complete (3 days) / User Confirm |
| `any` | `disputed` | User Complaint |
```

---

## Routes
```
# Buyer
POST /api/orders/checkout                      [auth]
GET  /api/orders                               [auth]
GET  /api/orders/{id}                          [auth]
POST /api/orders/{id}/cancel                   [auth]
POST /api/orders/{id}/confirm-received         [auth]
POST /api/orders/{id}/disputes                 [auth]

# Merchant
GET  /api/merchant/orders                      [auth:sanctum, merchant]
GET  /api/merchant/orders/{id}                 [auth:sanctum, merchant]
PUT  /api/merchant/orders/{id}/confirm         [auth:sanctum, merchant]
PUT  /api/merchant/orders/{id}/ship            [auth:sanctum, merchant]
```

---

## Events
| Event | Listener |
|---|---|
| `Order\OrderPlaced` | `SendOrderConfirmationEmail`, `NotifyMerchantNewOrder` |
| `Order\OrderCancelled` | `RestoreProductStock`, `ProcessRefundIfPaid` |
| `Order\OrderShipped` | `SendShippingNotification` |
| `Order\OrderDelivered` | `SendDeliveredNotification` |

---

## Files to Create
```
app/Http/Controllers/Api/Order/OrderController.php
app/Http/Requests/Order/CheckoutRequest.php
app/Http/Requests/Order/StoreDisputeRequest.php
app/Http/Requests/Order/ShipOrderRequest.php
app/Http/Resources/Order/OrderResource.php
app/Http/Resources/Order/OrderListResource.php
app/Http/Resources/Order/OrderItemResource.php
app/DTOs/Order/CheckoutDTO.php                      # address_id, items[], voucher_code?, notes?
app/Services/Order/OrderService.php
app/Models/Order.php
app/Models/OrderItem.php
app/Models/OrderStatusLog.php
app/Models/OrderDispute.php
app/Enums/OrderStatus.php
app/Events/Order/OrderPlaced.php
app/Events/Order/OrderCancelled.php
app/Events/Order/OrderShipped.php
app/Events/Order/OrderDelivered.php
app/Listeners/Order/SendOrderConfirmationEmail.php  (ShouldQueue)
app/Listeners/Order/RestoreProductStock.php         (ShouldQueue)
app/Listeners/Order/NotifyMerchantNewOrder.php      (ShouldQueue)
app/Jobs/CancelExpiredOrderJob.php
app/Mail/Order/OrderConfirmationMail.php
app/Mail/Order/OrderShippedMail.php
database/migrations/xxxx_create_orders_table.php
database/migrations/xxxx_create_order_items_table.php
database/migrations/xxxx_create_order_status_logs_table.php
database/migrations/xxxx_create_order_disputes_table.php
routes/api/order.php
tests/Feature/Api/Order/OrderTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `EmailService` | `OrderConfirmationMail`, `OrderShippedMail` |
| `IdempotencyService` | Mencegah double checkout |

---

## Business Logic Notes
- Checkout harus dalam satu **DB Transaction** — jika gagal satu langkah, semua di-rollback
- **Idempotency:** Endpoint `POST /api/orders/checkout` WAJIB mengirimkan `X-Idempotency-Key` untuk mencegah order ganda saat koneksi tidak stabil.
- `address_snapshot` & `product_snapshot`: simpan data saat checkout, bukan relasi — karena alamat dan produk bisa berubah di kemudian hari
- Stok dikurangi **saat order dibuat** (bukan saat bayar) — restore jika order expired/cancelled. Gunakan `DB::table('product_variants')->decrement('stock')` — observer akan otomatis sync `products.total_stock`
- `payment_due_at`: default 24 jam dari created_at, configurable
- Auto-cancel: `CancelExpiredOrderJob` di-dispatch ke queue, di-schedule via `app:cancel-expired-orders` artisan command
- Multi-store cart → multiple orders (satu order per toko) — `CartService::groupByStore()` return array per store, masing-masing jadi satu `Order`
- **Cart clearing:** Setelah checkout sukses, hapus semua `CartItem` dari cart user (`CartService::clear($user)`). Jangan hapus Cart record itu sendiri (permanent record per user).
- **Payment initiation:** Checkout endpoint hanya membuat Order (status `pending`). Client kemudian memanggil `POST /api/payments/initiate` dengan `order_id`. Ini memungkinkan user memilih metode bayar setelah order dibuat.
- **Voucher integration:** `CheckoutRequest` menerima optional `voucher_code`. Panggil `VoucherService::validate($code, $user, $subtotal)` sebelum commit order. Jika invalid, rollback dan return 422.
- **Shipping fee:** `CheckoutRequest` menerima `shipping_method` per store (kurir + service). `ShippingService::calculateCost()` dipanggil untuk validasi fee yang dikirim client vs hasil kalkulasi backend (toleransi ±Rp500 atau re-calculate di backend).
- Route model binding Order: gunakan **id** (bukan slug) — order number bisa ditampilkan di UI tapi binding tetap via primary key.
