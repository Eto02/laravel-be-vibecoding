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
}
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
GET  /api/merchant/orders                      [auth:merchant]
GET  /api/merchant/orders/{id}                 [auth:merchant]
PUT  /api/merchant/orders/{id}/confirm         [auth:merchant]
PUT  /api/merchant/orders/{id}/ship            [auth:merchant]
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
app/Services/Order/OrderService.php
app/Models/Order.php
app/Models/OrderItem.php
app/Models/OrderStatusLog.php
app/Models/OrderDispute.php
app/Enums/OrderStatus.php
app/Events/Order/OrderPlaced.php
app/Events/Order/OrderCancelled.php
app/Events/Order/OrderShipped.php
app/Listeners/Order/SendOrderConfirmationEmail.php
app/Listeners/Order/RestoreProductStock.php
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
| `NotificationService` | Push notif status pesanan berubah |

---

## Business Logic Notes
- Checkout harus dalam satu **DB Transaction** — jika gagal satu langkah, semua di-rollback
- `address_snapshot` & `product_snapshot`: simpan data saat checkout, bukan relasi — karena alamat dan produk bisa berubah di kemudian hari
- Stok dikurangi **saat order dibuat** (bukan saat bayar) — restore jika order expired/cancelled
- `payment_due_at`: default 24 jam dari created_at, configurable
- Auto-cancel: `CancelExpiredOrderJob` di-dispatch ke queue, di-schedule via `app:cancel-expired-orders` artisan command
- Multi-store cart → multiple orders (satu order per toko)
