# MODULE 5 — Cart & Wishlist
**Priority:** 🟠 P1 | **Status:** ✅ Selesai | **Sprint:** 5

---

## Yang Perlu Dibangun
- ⬜ Add/update/remove item ke cart
- ⬜ Cart persistence: **DB as source of truth** + Redis cache response (TTL 86400s)
- ⬜ Multi-store cart grouping via `store_id` di `cart_items` (denormalized)
- ⬜ Stock validation saat add to cart & checkout (re-validate dengan FOR UPDATE lock)
- ⬜ Wishlist CRUD
- ⬜ Check if product already in wishlist
- ⬜ `is_wishlisted` field di product detail response (saat authenticated)
- ⬜ Welcome Email (WelcomeMail + SendWelcomeEmail listener — carryover dari Sprint 1)

### Ditunda (Deferred)
- ❌ **Guest cart** — merge logic saat login terlalu kompleks untuk MVP. Require login sebelum cart. Defer ke post-P2.
- ❌ **Abandoned cart expiry job** — cleanup cart yang tidak aktif >30 hari. Defer ke P2.
- ❌ **Notifikasi harga turun untuk wishlist** — Defer ke Sprint 10 (Notification module).

---

## Entities

| Tabel | Kolom Utama |
|---|---|
| `carts` | `id`, `user_id` (unique), `timestamps` |
| `cart_items` | `id`, `cart_id`, `product_variant_id`, `product_id` *(denorm)*, `store_id` *(denorm)*, `quantity`, `price_snapshot` (integer cents), `timestamps` |
| `wishlists` | `id`, `user_id` (unique), `timestamps` |
| `wishlist_items` | `id`, `wishlist_id`, `product_id`, `timestamps` |

> **Catatan Desain:**
> - `carts.user_id` unique — satu user satu cart permanen (tidak perlu expires_at)
> - `cart_items.store_id` — denormalized dari `product.store_id` saat item ditambah. Dipakai untuk `groupByStore()` tanpa extra join
> - `cart_items.product_id` — denormalized untuk soft-delete handling (product soft-deleted tapi cart item masih bisa ditampilkan dengan warning)
> - `cart_items.price_snapshot` — integer cents, harga saat item ditambahkan. Tidak berubah meski harga produk berubah setelahnya
> - `wishlist_items` tidak perlu `added_at` custom — pakai `created_at` dari Eloquent timestamps
> - `wishlists.user_id` unique — satu wishlist per user (future: multi-wishlist jika dibutuhkan)

---

## Persistence Strategy

**Bukan dual-write.** Pattern yang dipakai:

```
Write path:  CartController → CartService → DB (source of truth) → invalidate Redis cache
Read path:   CartController → Redis cache hit? → return cached
                                    ↓ miss
                           DB → transform → cache di Redis → return
```

Redis key: `cart:user:{user_id}` TTL 86400s
Cache di-invalidate setiap kali ada perubahan (add/update/remove/clear).

---

## Enums / Flags

Tidak ada enum baru. Gunakan `ProductStatus` untuk validasi (hanya `active` product yang bisa di-add to cart).

---

## Routes

```
# Cart
GET    /api/cart                               [auth:sanctum]
POST   /api/cart/items                         [auth:sanctum]
PUT    /api/cart/items/{cartItemId}            [auth:sanctum]
DELETE /api/cart/items/{cartItemId}            [auth:sanctum]
DELETE /api/cart                               [auth:sanctum]  ← clear all (dipakai setelah checkout)

# Wishlist
GET    /api/wishlist                           [auth:sanctum]
POST   /api/wishlist/items                     [auth:sanctum]
DELETE /api/wishlist/items/{productId}         [auth:sanctum]
GET    /api/wishlist/items/{productId}/check   [auth:sanctum]  ← quick check untuk UI toggle
```

> **`is_wishlisted` di product detail:** `GET /api/products/{slug}` saat authenticated akan include
> field `is_wishlisted: bool` (query `wishlist_items` by authenticated user). Ini dilakukan di
> `ProductService::findBySlug(string $slug, ?User $user)` — jika `$user` null (guest), field tidak disertakan.

---

## DTOs

```php
// app/DTOs/Cart/AddCartItemDTO.php
readonly class AddCartItemDTO {
    public function __construct(
        public int $variantId,
        public int $quantity,
    ) {}

    public static function fromRequest(AddCartItemRequest $request): self
    {
        return new self(
            variantId: $request->integer('variant_id'),
            quantity:  $request->integer('quantity'),
        );
    }
}
```

---

## Files to Create

```
# DTOs
app/DTOs/Cart/AddCartItemDTO.php

# Controllers
app/Http/Controllers/Api/Cart/CartController.php
app/Http/Controllers/Api/Cart/WishlistController.php

# Form Requests
app/Http/Requests/Cart/AddCartItemRequest.php       ← variant_id (exists, active), quantity (min:1)
app/Http/Requests/Cart/UpdateCartItemRequest.php    ← quantity (min:1)

# Resources
app/Http/Resources/Cart/CartResource.php            ← total_items, total_price, grouped_by_store, items[]
app/Http/Resources/Cart/CartItemResource.php        ← variant info, price_snapshot, qty, subtotal, store_id
app/Http/Resources/Cart/WishlistResource.php        ← items[], total
app/Http/Resources/Cart/WishlistItemResource.php    ← product summary (id, name, slug, price, status)

# Services
app/Services/Cart/CartService.php
app/Services/Cart/WishlistService.php

# Models
app/Models/Cart.php
app/Models/CartItem.php
app/Models/Wishlist.php
app/Models/WishlistItem.php

# Migrations
database/migrations/xxxx_create_carts_table.php
database/migrations/xxxx_create_cart_items_table.php
database/migrations/xxxx_create_wishlists_table.php
database/migrations/xxxx_create_wishlist_items_table.php

# Routes
routes/api/cart.php

# Welcome Email (carryover Sprint 1)
app/Mail/Auth/WelcomeMail.php
app/Listeners/Auth/SendWelcomeEmail.php             ← listen UserRegistered, ShouldQueue

# Tests
tests/Feature/Api/Cart/CartTest.php
tests/Feature/Api/Cart/WishlistTest.php
tests/Unit/Services/Cart/CartServiceTest.php
```

---

## Shared Services Needed

| Service | Kegunaan |
|---|---|
| `CacheService` | Cache cart response: `cart:user:{user_id}` TTL 86400s |

---

## Business Logic Notes

### Cart Lifecycle
- Cart dibuat **otomatis** saat user pertama kali add item (tidak perlu endpoint create cart)
- `Cart` adalah permanent record per user — tidak dihapus setelah checkout, hanya items-nya yang di-clear
- Satu user satu cart (`unique('user_id')` di migration)

### Add to Cart
1. Cari atau buat `Cart` untuk user (`Cart::firstOrCreate(['user_id' => $user->id])`)
2. Validasi variant: exists, product status `active`, stock tersedia
3. Jika variant sudah ada di cart → increment quantity (bukan duplicate item)
4. `price_snapshot` = `$variant->price` saat itu
5. Denormalize `store_id = $variant->product->store_id`, `product_id = $variant->product_id`
6. Invalidate Redis cache `cart:user:{user_id}`

### Update Quantity
- Validasi qty baru tidak melebihi `variant.stock` saat ini
- Jika qty = 0 → hapus item (atau reject, tergantung UX preference) → **reject dengan 422**, suruh client pakai DELETE

### Stock Validation Behavior
- **Saat add/update:** blokir jika `qty > variant.stock` (422 + pesan "Stok tersedia: X")
- **Saat checkout (Sprint 6):** re-validasi dengan `SELECT FOR UPDATE` untuk mencegah race condition. Jika stok tidak cukup → rollback + 422 "Stok {nama produk} tersisa {X}, ubah qty sebelum checkout"
- **Tidak auto-adjust qty** — biarkan cart item tetap di qty lama, validasi hanya di entry points

### Product Soft-Delete Handling
Jika product di-soft-delete setelah ada di cart:
- Cart item tetap ada di DB
- `CartResource` tandai item dengan `is_available: false` + pesan "Produk tidak tersedia"
- Tidak bisa di-checkout (filtered out di `groupByStore()`)

### GroupByStore (untuk checkout — Sprint 6)
```php
// CartService::groupByStore(Cart $cart): array
// Return: [store_id => ['store' => Store, 'items' => CartItem[]]]
// Filter out items dengan is_available = false (product soft-deleted)
// Pakai store_id yang sudah di-denormalize — tidak butuh join ke products
```

### Cart Response Shape
```json
{
  "data": {
    "total_items": 3,
    "total_price_cents": 450000,
    "total_price": "Rp 4.500",
    "stores": [
      {
        "store_id": 1,
        "store_name": "Toko A",
        "store_slug": "toko-a",
        "items": [
          {
            "id": 12,
            "product_id": 5,
            "product_name": "Sepatu Nike",
            "product_slug": "sepatu-nike",
            "variant_id": 8,
            "variant_sku": "NK-RED-42",
            "variant_attributes": {"warna": "Merah", "ukuran": "42"},
            "quantity": 2,
            "price_snapshot_cents": 150000,
            "price_snapshot": "Rp 1.500",
            "subtotal_cents": 300000,
            "subtotal": "Rp 3.000",
            "is_available": true,
            "current_stock": 10,
            "thumbnail_url": "https://..."
          }
        ],
        "store_subtotal_cents": 300000,
        "store_subtotal": "Rp 3.000"
      }
    ]
  }
}
```

### Wishlist
- Satu user satu wishlist (auto-created on first add, seperti cart)
- Produk yang sama tidak bisa ditambah dua kali (unique constraint `wishlist_id + product_id`)
- Wishlist item simpan `product_id` (bukan variant) — buyer belum pilih ukuran/warna
- `GET /api/wishlist` return produk dengan info terkini (nama, harga terbaru, stok, status)
- Produk yang di-soft-delete tetap ada di wishlist tapi ditandai `is_available: false`

### Welcome Email (carryover Sprint 1)
```php
// app/Listeners/Auth/SendWelcomeEmail.php
class SendWelcomeEmail implements ShouldQueue {
    public function handle(UserRegistered $event): void {
        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }
}
// Register di AppServiceProvider::boot():
// Event::listen(UserRegistered::class, SendWelcomeEmail::class);
```

---

## Test Scenarios

### CartTest (Feature)
- `test_guest_cannot_access_cart`
- `test_authenticated_user_can_add_item_to_cart`
- `test_adding_same_variant_increments_quantity`
- `test_cannot_add_item_exceeding_stock`
- `test_cannot_add_inactive_product_to_cart`
- `test_user_can_update_cart_item_quantity`
- `test_update_quantity_to_zero_returns_422`
- `test_user_can_remove_cart_item`
- `test_user_can_clear_cart`
- `test_cart_is_grouped_by_store`
- `test_soft_deleted_product_marked_unavailable_in_cart`

### WishlistTest (Feature)
- `test_guest_cannot_access_wishlist`
- `test_user_can_add_product_to_wishlist`
- `test_cannot_add_same_product_twice_to_wishlist`
- `test_user_can_remove_product_from_wishlist`
- `test_wishlist_check_returns_true_if_product_wishlisted`
- `test_wishlist_check_returns_false_if_not_wishlisted`
- `test_product_detail_includes_is_wishlisted_when_authenticated`
- `test_product_detail_omits_is_wishlisted_for_guest`

### CartServiceTest (Unit)
- `test_add_item_creates_cart_if_not_exists`
- `test_add_item_increments_quantity_for_duplicate_variant`
- `test_group_by_store_returns_correct_structure`
- `test_group_by_store_excludes_unavailable_items`

---

## Checklist Eksekusi

- [ ] Tambah `WelcomeMail` + `SendWelcomeEmail` listener (carryover Sprint 1)
- [ ] Buat migrations (carts, cart_items dengan store_id+product_id, wishlists, wishlist_items)
- [ ] Buat Models + Factories (Cart, CartItem, Wishlist, WishlistItem)
- [ ] Buat `AddCartItemDTO`
- [ ] Buat `CartService` dengan `add()`, `update()`, `remove()`, `clear()`, `groupByStore()`, `getForUser()` (DB + Redis cache)
- [ ] Buat `WishlistService` dengan `add()`, `remove()`, `check()`, `getForUser()`
- [ ] Update `ProductService::findBySlug()` terima optional `?User $user` → tambah `is_wishlisted` field
- [ ] Buat Controllers + FormRequests + Resources
- [ ] Buat routes/api/cart.php
- [ ] Buat Feature Tests + Unit Tests
- [ ] Update Postman collection (folder "06. Cart")
- [ ] Update planning/05-cart.md → Status ✅ Selesai
