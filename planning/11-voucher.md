# MODULE 11 — Voucher & Promotions
**Priority:** 🟡 P2 | **Status:** ⬜ Belum | **Sprint:** 11

---

## Yang Perlu Dibangun
- ⬜ Voucher/Coupon code — diskon fixed/percentage, min belanja, batas penggunaan
- ⬜ Free shipping voucher (platform-wide atau per toko)
- ⬜ Flash Sale — harga coret, countdown timer, stok terbatas
- ⬜ Cashback — % dari total pembelian dikreditkan ke wallet setelah order `completed`
- ⬜ Loyalty Points — kumpul dari setiap pembelian, tukar dengan diskon
- ⬜ Voucher validation saat checkout

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `vouchers` | `code`, `type` (fixed/percentage/free_shipping), `scope` (platform/store), `store_id` (nullable), `value`, `min_purchase`, `max_discount`, `usage_limit`, `used_count`, `valid_from`, `valid_until`, `is_active` |
| `voucher_usages` | `voucher_id`, `user_id`, `order_id`, `discount_amount`, `used_at` |
| `flash_sales` | `store_id` (nullable), `name`, `starts_at`, `ends_at`, `is_active` |
| `flash_sale_products` | `flash_sale_id`, `product_variant_id`, `sale_price`, `original_price`, `quota`, `sold_count` |
| `loyalty_points` | `user_id`, `balance`, `lifetime_earned` |
| `point_transactions` | `loyalty_point_id`, `type` (earn/redeem/expire), `amount`, `description`, `reference_type`, `reference_id`, `expires_at` |

---

## Routes
```
# Buyer
POST /api/vouchers/validate                    [auth]
GET  /api/vouchers/available                   [auth]
GET  /api/flash-sales                          [public]
GET  /api/flash-sales/{id}                     [public]
GET  /api/flash-sales/{id}/products            [public]
GET  /api/loyalty/points                       [auth]
GET  /api/loyalty/transactions                 [auth]
POST /api/loyalty/redeem                       [auth]

# Merchant
GET|POST /api/merchant/vouchers                [auth:merchant]
PUT|DELETE /api/merchant/vouchers/{id}         [auth:merchant]
GET|POST /api/merchant/flash-sales             [auth:merchant]
PUT /api/merchant/flash-sales/{id}             [auth:merchant]

# Admin
GET|POST /api/admin/vouchers                   [auth:admin]
GET|POST /api/admin/flash-sales                [auth:admin]
```

---

## Files to Create
```
app/Http/Controllers/Api/Voucher/VoucherController.php
app/Http/Controllers/Api/Voucher/FlashSaleController.php
app/Http/Controllers/Api/Voucher/LoyaltyController.php
app/Http/Requests/Voucher/ValidateVoucherRequest.php
app/Http/Requests/Voucher/StoreVoucherRequest.php
app/Http/Resources/Voucher/VoucherResource.php
app/Http/Resources/Voucher/FlashSaleResource.php
app/Http/Resources/Voucher/LoyaltyResource.php
app/Services/Voucher/VoucherService.php
app/Services/Voucher/FlashSaleService.php
app/Services/Voucher/LoyaltyService.php
app/Models/Voucher.php
app/Models/VoucherUsage.php
app/Models/FlashSale.php
app/Models/FlashSaleProduct.php
app/Models/LoyaltyPoint.php
app/Models/PointTransaction.php
database/migrations/xxxx_create_vouchers_table.php
database/migrations/xxxx_create_flash_sales_table.php
database/migrations/xxxx_create_loyalty_points_table.php
routes/api/voucher.php
tests/Feature/Api/Voucher/VoucherTest.php
tests/Feature/Api/Voucher/FlashSaleTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `CacheService` | Cache flash sale data & countdown (TTL pendek ~60s) |
| `NotificationService` | Alert flash sale akan mulai, voucher hampir habis |

---

## Business Logic Notes
- Flash sale quota: kurangi `sold_count` secara atomic via Redis `INCR` untuk menghindari race condition
- Cashback: di-credit ke wallet setelah order `completed`, bukan saat bayar (via Event Listener)
- Loyalty points expire setelah 1 tahun jika tidak dipakai
- Voucher validation saat checkout: cek eligibility, sisa quota, min purchase, validity date
- Satu order hanya bisa pakai satu voucher (kecuali ada rule berbeda)
- Flash sale product: `sale_price` harus lebih kecil dari `original_price`
