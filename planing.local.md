# Marketplace API — Full Module Planning

> Stack: Laravel 13 · MySQL · Redis · Xendit · Docker
> Arsitektur: Domain-Driven Modular Monolith (lihat CLAUDE.md)

---

## Status Legend
- ⬜ Belum dimulai
- 🟡 Partial / In Progress  
- ✅ Selesai

---

## MODULE 1 — Auth & Identity
**Priority:** 🔴 P0 | **Status:** 🟡 Partial

### Yang Sudah Ada
- ✅ Register, Login (email/password)
- ✅ OAuth (Google, GitHub via Socialite)
- ✅ Refresh Token Rotation (custom)
- ✅ `/api/auth/me`

### Yang Perlu Dibangun
- ⬜ Email Verification — kirim link saat register, blokir login jika belum verify
- ⬜ Resend Verification Email
- ⬜ Forgot Password (kirim reset link via email)
- ⬜ Reset Password (validasi token + simpan password baru)
- ⬜ Change Password (untuk user yang sudah login)
- ⬜ Active Session List + Logout per device

### Entities
`users`, `refresh_tokens`, `oauth_accounts`, `password_reset_tokens`

### Routes
```
POST /api/auth/email/verify
POST /api/auth/email/resend
POST /api/auth/forgot-password
POST /api/auth/reset-password
PUT  /api/auth/change-password     [auth]
GET  /api/auth/sessions            [auth]
DELETE /api/auth/sessions/{id}     [auth]
```

### Shared Services Needed
- `EmailService` — kirim `EmailVerificationMail`, `PasswordResetMail`
- `OtpService` — jika OTP email dipakai (alternatif link)

---

## MODULE 2 — User Profile
**Priority:** 🟠 P1 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Get/Update profile (nama, foto, bio, nomor HP)
- ⬜ Upload foto profil (via `MediaService`)
- ⬜ Address Book — CRUD alamat, set default
- ⬜ Phone Verification (OTP via SMS/WA)
- ⬜ Notification Preferences

### Entities
`user_profiles` (atau extend `users`), `addresses`, `phone_verifications`

### Routes
```
GET|PUT  /api/users/me                         [auth]
POST     /api/users/me/avatar                  [auth]
GET|POST /api/users/me/addresses               [auth]
PUT|DELETE /api/users/me/addresses/{id}        [auth]
POST     /api/users/me/addresses/{id}/default  [auth]
POST     /api/users/phone/send-otp             [auth]
POST     /api/users/phone/verify               [auth]
```

### Shared Services Needed
- `MediaService` — upload foto profil ke S3
- `OtpService` — generate & verify OTP HP
- `SmsService` — kirim OTP via WA/SMS

---

## MODULE 3 — Merchant / Store
**Priority:** 🟠 P1 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Store Registration (nama toko, slug, deskripsi, logo, alamat toko)
- ⬜ Store Public Profile (produk, info toko, rating)
- ⬜ Store Settings (jam operasional, auto-reply)
- ⬜ KYC Document Upload (KTP, NPWP)
- ⬜ Store Analytics (revenue, produk terlaris per periode)
- ⬜ Store Followers (ikuti/batal ikuti toko)
- ⬜ Merchant Dashboard summary

### Entities
`stores`, `store_documents`, `store_followers`, `store_operational_hours`

### Routes
```
POST /api/merchant/register                    [auth]
GET|PUT /api/merchant/store                    [auth:merchant]
GET /api/merchant/analytics                    [auth:merchant]
GET /api/merchant/dashboard                    [auth:merchant]
POST /api/merchant/kyc                         [auth:merchant]
GET /api/stores/{slug}                         [public]
POST /api/stores/{slug}/follow                 [auth]
DELETE /api/stores/{slug}/follow               [auth]
```

### Shared Services Needed
- `MediaService` — upload logo toko & dokumen KYC
- `EmailService` — notifikasi KYC approved/rejected

---

## MODULE 4 — Product Catalog
**Priority:** 🟠 P1 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Category Tree (hirarkis, max 3 level)
- ⬜ Product CRUD (merchant only)
- ⬜ Product Variants (kombinasi atribut: Warna × Ukuran)
- ⬜ Product Attributes (key-value fleksibel)
- ⬜ Product Media (multiple foto, urutan drag-drop)
- ⬜ Inventory per variant (stok, SKU, berat)
- ⬜ Product Status: draft → active → inactive → banned
- ⬜ Public product listing + filter (harga, kategori, rating, lokasi)
- ⬜ Merchant product listing (semua statusnya)

### Entities
`categories`, `products`, `product_variants`, `product_media`, `product_attributes`, `attribute_values`

### Routes
```
GET /api/categories                            [public]
GET /api/products                              [public] ?search=&category=&min_price=&max_price=&sort=
GET /api/products/{id}                         [public]
POST|GET /api/merchant/products                [auth:merchant]
PUT|DELETE /api/merchant/products/{id}         [auth:merchant]
POST /api/merchant/products/{id}/media         [auth:merchant]
DELETE /api/merchant/products/{id}/media/{mediaId} [auth:merchant]
PUT /api/merchant/products/{id}/variants       [auth:merchant]
```

### Shared Services Needed
- `MediaService` — upload foto produk ke S3
- `CacheService` — cache category tree (TTL 3600s), product list (TTL 300s)

---

## MODULE 5 — Cart & Wishlist
**Priority:** 🟠 P1 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Add/update/remove item ke cart
- ⬜ Cart persistence: Redis (session) + DB (permanent)
- ⬜ Multi-store cart grouping (group by merchant)
- ⬜ Stock validation saat checkout
- ⬜ Wishlist CRUD
- ⬜ Notifikasi harga turun untuk wishlist item

### Entities
`carts`, `cart_items`, `wishlists`, `wishlist_items`

### Routes
```
GET /api/cart                                  [auth]
POST /api/cart/items                           [auth]
PUT /api/cart/items/{id}                       [auth]
DELETE /api/cart/items/{id}                    [auth]
DELETE /api/cart                               [auth]
GET|POST /api/wishlist                         [auth]
DELETE /api/wishlist/{productId}               [auth]
```

### Shared Services Needed
- `CacheService` — Redis cart storage
- `NotificationService` — alert harga turun

---

## MODULE 6 — Order Management
**Priority:** 🟠 P1 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Checkout (pilih alamat, kurir, payment method, apply voucher)
- ⬜ Create order dari cart (kurangi stok otomatis)
- ⬜ Order status flow: `pending → paid → processing → shipped → delivered → completed`
- ⬜ Auto-cancel jika belum dibayar (Queue Job + scheduler)
- ⬜ Merchant: terima/tolak pesanan, input resi
- ⬜ Buyer: cancel order (jika masih `pending`/`paid`)
- ⬜ Order Dispute
- ⬜ Order history + filter by status

### Entities
`orders`, `order_items`, `order_status_logs`, `order_disputes`

### Routes
```
POST /api/orders/checkout                      [auth]
GET /api/orders                                [auth]
GET /api/orders/{id}                           [auth]
POST /api/orders/{id}/cancel                   [auth]
POST /api/orders/{id}/disputes                 [auth]
GET /api/merchant/orders                       [auth:merchant]
PUT /api/merchant/orders/{id}/status           [auth:merchant]
POST /api/merchant/orders/{id}/shipment        [auth:merchant]
```

### Events
`Order\OrderPlaced`, `Order\OrderCancelled`, `Order\OrderShipped`

### Shared Services Needed
- `NotificationService` — notif status pesanan (email + push)
- `EmailService` — `OrderConfirmationMail`, `OrderShippedMail`

---

## MODULE 7 — Payment
**Priority:** 🟠 P1 | **Status:** 🟡 Partial (Xendit Invoice + Webhook)

### Yang Sudah Ada
- ✅ Xendit Invoice creation
- ✅ Xendit Webhook handler
- ✅ `Transaction` model + `TransactionStatus` enum

### Yang Perlu Dibangun
- ⬜ Multiple payment methods (VA, QRIS, e-wallet)
- ⬜ Payment expiry auto-cancel (Job + scheduler)
- ⬜ Digital Wallet / Saldo internal
- ⬜ Wallet top-up
- ⬜ Refund ke saldo/rekening
- ⬜ Payment receipt (PDF/email)
- ⬜ Midtrans integration (interface sudah ada, tinggal implementasi)

### Entities
`transactions` (ada), `payments`, `refunds`, `wallet_balances`, `wallet_transactions`

### Routes
```
POST /api/payments/initiate                    [auth]
GET /api/payments/{id}/status                  [auth]
POST /api/payments/{provider}/webhook          [public, verified]
POST /api/payments/{id}/refund                 [auth]
GET /api/wallet/balance                        [auth]
GET /api/wallet/transactions                   [auth]
POST /api/wallet/topup                         [auth]
```

### Events
`Payment\PaymentCaptured`, `Payment\PaymentFailed`, `Payment\RefundProcessed`

### Shared Services Needed
- `EmailService` — `PaymentSuccessMail`, receipt
- `NotificationService` — push notif pembayaran

---

## MODULE 8 — Shipping & Logistics
**Priority:** 🟠 P1 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Ongkir calculation (RajaOngkir/Biteship API)
- ⬜ Available courier list berdasarkan origin-destination
- ⬜ EDD (Estimated Delivery Date)
- ⬜ AWB/Resi generation setelah merchant input
- ⬜ Package tracking real-time
- ⬜ Shipment status sync (via webhook atau polling)

### Entities
`shipments`, `shipment_trackings`, `shipping_rates`

### Interface
`ShippingProviderInterface` → `RajaOngkirService`, `BiteshipService`

### Routes
```
POST /api/shipping/calculate                   [auth]
GET /api/shipping/couriers                     [public]
GET /api/shipments/{trackingNumber}            [public]
GET /api/shipments/{id}/tracking               [auth]
```

### Shared Services Needed
- `NotificationService` — update tracking via push/email

---

## MODULE 9 — Review & Rating
**Priority:** 🟡 P2 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Product review (bintang 1-5, komentar, foto/video)
- ⬜ Review gate — hanya bisa review setelah order `delivered`
- ⬜ Merchant reply to review
- ⬜ Review moderation (admin approve/reject)
- ⬜ Review helpfulness vote
- ⬜ Store rating aggregation (average dari semua review produknya)

### Entities
`reviews`, `review_media`, `review_replies`, `review_votes`

### Routes
```
GET /api/products/{id}/reviews                 [public]
POST /api/orders/{orderId}/reviews             [auth]
POST /api/reviews/{id}/replies                 [auth:merchant]
POST /api/reviews/{id}/votes                   [auth]
```

### Shared Services Needed
- `MediaService` — upload foto/video review
- `NotificationService` — notif merchant ada review baru

---

## MODULE 10 — Notification System
**Priority:** 🟡 P2 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ In-app notification CRUD (bell icon, mark read)
- ⬜ Push notification via FCM (mobile)
- ⬜ Email notification (sudah partial via EmailService)
- ⬜ WhatsApp notification (Fonnte/Meta API)
- ⬜ Notification preferences (user pilih channel)
- ⬜ Push token registration (device token FCM)

### Entities
`notifications`, `notification_preferences`, `push_tokens`

### Routes
```
GET /api/notifications                         [auth]
PUT /api/notifications/{id}/read               [auth]
PUT /api/notifications/read-all                [auth]
DELETE /api/notifications/{id}                 [auth]
POST /api/notifications/push-token             [auth]
GET|PUT /api/notifications/preferences         [auth]
```

### Shared Services (Core dari module ini)
- `NotificationService` — orchestrator (interface-based)
- `PushNotificationService` — FCM wrapper
- `SmsService` — WA/SMS wrapper
- `EmailService` — sudah ada

---

## MODULE 11 — Voucher & Promotions
**Priority:** 🟡 P2 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Voucher/Coupon code (fixed/percentage, min belanja, batas pakai)
- ⬜ Gratis ongkir voucher
- ⬜ Flash Sale (harga coret, countdown, stok terbatas)
- ⬜ Cashback (% ke wallet setelah transaksi selesai)
- ⬜ Loyalty Points (kumpul dari setiap pembelian, tukar diskon)
- ⬜ Voucher validation saat checkout

### Entities
`vouchers`, `voucher_usages`, `flash_sales`, `flash_sale_products`, `loyalty_points`, `point_transactions`

### Routes
```
POST /api/vouchers/validate                    [auth]
GET /api/flash-sales                           [public]
GET /api/flash-sales/{id}/products             [public]
GET /api/loyalty/points                        [auth]
POST /api/loyalty/redeem                       [auth]
GET|POST /api/admin/vouchers                   [auth:admin]
GET|POST /api/admin/flash-sales                [auth:admin]
```

### Shared Services Needed
- `CacheService` — cache flash sale data (Redis, TTL pendek)
- `NotificationService` — alert voucher hampir habis

---

## MODULE 12 — Admin Panel API
**Priority:** 🟡 P2 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ User management (list, ban, suspend, verify)
- ⬜ Merchant management (approve/reject toko, verifikasi KYC)
- ⬜ Product moderation (approve/reject/ban produk)
- ⬜ Review moderation (approve/reject)
- ⬜ Dispute resolution
- ⬜ Platform revenue dashboard (komisi per transaksi)
- ⬜ Platform settings (komisi %, batas produk, dll)

### Routes (semua `[auth:admin]`)
```
GET|PUT /api/admin/users/{id}
PUT /api/admin/users/{id}/ban
GET /api/admin/merchants
PUT /api/admin/merchants/{id}/verify
GET|PUT /api/admin/products/{id}/status
GET /api/admin/orders
GET /api/admin/revenue
GET|PUT /api/admin/disputes/{id}
GET|PUT /api/admin/settings
```

### Dependencies
- `spatie/laravel-permission` — role: `buyer`, `merchant`, `admin`

---

## MODULE 13 — Search & Discovery
**Priority:** 🟢 P3 | **Status:** ⬜ Belum

### Yang Perlu Dibangun
- ⬜ Full-text search produk (Laravel Scout + Meilisearch)
- ⬜ Filter: harga, kategori, rating, lokasi, pengiriman
- ⬜ Search autocomplete (Redis-cached, top 10 saran)
- ⬜ Search history per user
- ⬜ Trending products (based on view/purchase count)
- ⬜ "Mungkin kamu suka" rekomendasi sederhana

### Routes
```
GET /api/search?q=&filters=                    [public]
GET /api/search/autocomplete?q=                [public]
GET /api/search/trending                       [public]
GET|DELETE /api/search/history                 [auth]
```

### Dependencies
- `laravel/scout`
- `meilisearch/meilisearch-php`

### Shared Services Needed
- `CacheService` — cache autocomplete & trending

---

## Shared Services Planning

Semua shared service di `app/Services/Shared/` — **perlu dibuat sebelum modul P2:**

| Service | Interface | Dipakai Oleh | Priority |
|---|---|---|---|
| `EmailService` | ✅ Butuh interface | Auth, Order, Payment, Review | 🔴 P0 |
| `OtpService` | ✅ Butuh interface | Auth, User | 🟠 P1 |
| `MediaService` | ✅ Butuh interface | User, Merchant, Product, Review | 🟠 P1 |
| `CacheService` | ✅ Butuh interface | Product, Cart, Search, Voucher | 🟠 P1 |
| `SmsService` | ✅ Butuh interface | User (phone verify), Notification | 🟡 P2 |
| `PushNotificationService` | ✅ Butuh interface | Notification | 🟡 P2 |
| `NotificationService` | ✅ Butuh interface | Order, Payment, Review, dll | 🟡 P2 |

---

## Sprint Roadmap

| Sprint | Target | Modul |
|---|---|---|
| Sprint 1 | Shared Services Dasar | EmailService, OtpService, MediaService |
| Sprint 2 | Auth Completion | Email Verify, Forgot/Reset Password |
| Sprint 3 | User Profile | Profile, Address Book, Phone Verify |
| Sprint 4 | Merchant + Category | Store Registration, Category Tree |
| Sprint 5 | Product Catalog | CRUD, Variant, Media, Inventory |
| Sprint 6 | Cart + Wishlist | Cart, Wishlist |
| Sprint 7 | Order | Checkout, Status Flow, Cancel |
| Sprint 8 | Payment Completion | Multi-method, Wallet, Refund |
| Sprint 9 | Shipping | RajaOngkir, AWB, Tracking |
| Sprint 10 | Review | Rating, Reply, Media |
| Sprint 11 | Notification | FCM, WA, In-app, Preferences |
| Sprint 12 | Voucher + Promo | Coupon, Flash Sale, Loyalty |
| Sprint 13 | Admin Panel | Moderation, Dashboard, KYC |
| Sprint 14 | Search | Meilisearch, Autocomplete, Trending |

---

## Dependency Graph

```
Auth ──────────────────────────────► User Profile
Auth ──────────────────────────────► Merchant/Store
Merchant ──────────────────────────► Product Catalog
Product ───────────────────────────► Cart
Cart ──────────────────────────────► Order
Order ─────────────────────────────► Payment
Order ─────────────────────────────► Shipping
Order+Shipping ────────────────────► Review
Review+Order+Payment ──────────────► Notification
Order+Product ─────────────────────► Voucher
All Modules ───────────────────────► Admin Panel
Product ───────────────────────────► Search (P3)
```

---

## Aturan Pengerjaan

1. **Satu sprint = satu modul.** Jangan mulai modul baru sebelum sprint sebelumnya selesai (termasuk test).
2. **Buat Shared Services dulu** sebelum modul yang butuh mereka.
3. **Setiap modul wajib punya Feature Test** sebelum dianggap selesai.
4. **Selalu buat branch baru** dari `main` untuk setiap sprint: `feat/{sprint}-{modul-name}`.
5. **PR wajib passing semua test** sebelum merge ke `main`.
