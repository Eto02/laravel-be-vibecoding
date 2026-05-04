# MODULE 5 — Cart & Wishlist
**Priority:** 🟠 P1 | **Status:** ⬜ Belum | **Sprint:** 5

---

## Yang Perlu Dibangun
- ⬜ Add/update/remove item ke cart
- ⬜ Cart persistence: Redis (fast read) + DB (permanent backup)
- ⬜ Multi-store cart grouping (group by merchant saat checkout)
- ⬜ Stock validation saat add to cart & checkout
- ⬜ Wishlist CRUD
- ⬜ Check if product already in wishlist
- ⬜ Notifikasi harga turun untuk wishlist item (P2)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `carts` | `user_id`, `expires_at` |
| `cart_items` | `cart_id`, `product_variant_id`, `quantity`, `price_snapshot` (harga saat ditambah) |
| `wishlists` | `user_id` |
| `wishlist_items` | `wishlist_id`, `product_id`, `added_at` |

---

## Routes
```
GET    /api/cart                               [auth]
POST   /api/cart/items                         [auth]
PUT    /api/cart/items/{cartItemId}            [auth]
DELETE /api/cart/items/{cartItemId}            [auth]
DELETE /api/cart                               [auth] (clear all)

GET    /api/wishlist                           [auth]
POST   /api/wishlist/items                     [auth]
DELETE /api/wishlist/items/{productId}         [auth]
GET    /api/wishlist/items/{productId}/check   [auth]
```

---

## Files to Create
```
app/Http/Controllers/Api/Cart/CartController.php
app/Http/Controllers/Api/Cart/WishlistController.php
app/Http/Requests/Cart/AddCartItemRequest.php
app/Http/Requests/Cart/UpdateCartItemRequest.php
app/Http/Resources/Cart/CartResource.php
app/Http/Resources/Cart/CartItemResource.php
app/Http/Resources/Cart/WishlistResource.php
app/Services/Cart/CartService.php
app/Services/Cart/WishlistService.php
app/Models/Cart.php
app/Models/CartItem.php
app/Models/Wishlist.php
app/Models/WishlistItem.php
database/migrations/xxxx_create_carts_table.php
database/migrations/xxxx_create_cart_items_table.php
database/migrations/xxxx_create_wishlists_table.php
database/migrations/xxxx_create_wishlist_items_table.php
routes/api/cart.php
tests/Feature/Api/Cart/CartTest.php
tests/Feature/Api/Cart/WishlistTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `CacheService` | Redis cart storage (key: `cart:{user_id}`, TTL: 86400s) |

---

## Business Logic Notes
- `price_snapshot`: simpan harga saat item ditambahkan — harga produk bisa berubah tapi snapshot tetap
- Cart item qty tidak boleh melebihi stok produk saat ini
- Guest cart: simpan di Redis dengan session key, merge ke user cart saat login
- Multi-store grouping: di-handle di `CartService::groupByStore()` saat checkout, bukan di DB
- Wishlist item: tidak ada batas jumlah, tapi produk yang sama tidak bisa ditambah dua kali
