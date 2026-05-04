# Planning Index — Marketplace API

> Semua file planning modul tersimpan di folder ini.
> Setiap modul memiliki file terpisah untuk kemudahan navigasi dan tracking progress.

## Status Legend
- ⬜ Belum dimulai
- 🟡 In Progress
- ✅ Selesai

## Modules

| File | Modul | Priority | Status |
|---|---|---|---|
| [00-shared-services.md](./00-shared-services.md) | Shared Services | 🔴 P0 | ⬜ |
| [01-auth.md](./01-auth.md) | Auth & Identity | 🔴 P0 | 🟡 |
| [02-user.md](./02-user.md) | User Profile | 🟠 P1 | ⬜ |
| [03-merchant.md](./03-merchant.md) | Merchant / Store | 🟠 P1 | ⬜ |
| [04-product.md](./04-product.md) | Product Catalog | 🟠 P1 | ⬜ |
| [05-cart.md](./05-cart.md) | Cart & Wishlist | 🟠 P1 | ⬜ |
| [06-order.md](./06-order.md) | Order Management | 🟠 P1 | ⬜ |
| [07-payment.md](./07-payment.md) | Payment | 🟠 P1 | 🟡 |
| [08-shipping.md](./08-shipping.md) | Shipping & Logistics | 🟠 P1 | ⬜ |
| [09-review.md](./09-review.md) | Review & Rating | 🟡 P2 | ⬜ |
| [10-notification.md](./10-notification.md) | Notification System | 🟡 P2 | ⬜ |
| [11-voucher.md](./11-voucher.md) | Voucher & Promotions | 🟡 P2 | ⬜ |
| [12-admin.md](./12-admin.md) | Admin Panel | 🟡 P2 | ⬜ |
| [13-search.md](./13-search.md) | Search & Discovery | 🟢 P3 | ⬜ |

## Sprint Roadmap

| Sprint | Target | Modul |
|---|---|---|
| Sprint 0 | Shared Services Dasar | EmailService, OtpService, MediaService, CacheService |
| Sprint 1 | Auth Completion | Email Verify, Forgot/Reset Password, Change Password |
| Sprint 2 | User Profile | Profile, Address Book, Phone Verify |
| Sprint 3 | Merchant + Category | Store Registration, Category Tree |
| Sprint 4 | Product Catalog | CRUD, Variant, Media, Inventory |
| Sprint 5 | Cart + Wishlist | Cart, Wishlist |
| Sprint 6 | Order | Checkout, Status Flow, Cancel, Dispute |
| Sprint 7 | Payment Completion | Multi-method, Wallet, Refund |
| Sprint 8 | Shipping | RajaOngkir, AWB, Tracking |
| Sprint 9 | Review | Rating, Reply, Media Review |
| Sprint 10 | Notification | FCM, WA, In-app, Preferences |
| Sprint 11 | Voucher + Promo | Coupon, Flash Sale, Loyalty Points |
| Sprint 12 | Admin Panel | Moderation, Dashboard, KYC, Dispute |
| Sprint 13 | Search | Meilisearch, Autocomplete, Trending |

## Dependency Graph

```
Shared Services ──► Auth ──────────────────────────────► User
                    Auth ──────────────────────────────► Merchant
                    Merchant ──────────────────────────► Product
                    Product ───────────────────────────► Cart
                    Cart ──────────────────────────────► Order
                    Order ─────────────────────────────► Payment
                    Order ─────────────────────────────► Shipping
                    Order + Shipping ───────────────────► Review
                    All ────────────────────────────────► Notification
                    Order + Product ────────────────────► Voucher
                    All Modules ────────────────────────► Admin
                    Product ────────────────────────────► Search (P3)
```
