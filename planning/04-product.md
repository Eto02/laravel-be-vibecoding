# MODULE 4 — Product Catalog
**Priority:** 🟠 P1 | **Status:** ✅ Selesai | **Sprint:** 4

---

## Yang Perlu Dibangun (Sprint 4 Scope)
- ⬜ Category Tree — hirarkis max 3 level (Elektronik → Handphone → Android), nested response
- ⬜ Product CRUD — merchant only, dengan status lifecycle
- ⬜ Product Variants — CRUD individual per variant, kombinasi atribut via JSON
- ⬜ Product Media — presigned URL flow, multiple foto, urutan bisa diatur
- ⬜ Inventory per variant — stok, SKU, berat, dimensi
- ⬜ Product Status lifecycle: `draft → active → inactive` (banned via Admin only)
- ⬜ Public product listing dengan filter & sorting + pagination
- ⬜ Merchant product management (semua status milik sendiri)
- ⬜ Implementasi `GET /api/stores/{slug}/products` (sebelumnya 501 stub)

### Ditunda (Deferred)
- ❌ **`product_attributes` & `attribute_values`** — attribute system per kategori, terlalu kompleks untuk Sprint 4. Variant pakai JSON `attributes` di `product_variants`. Defer ke Sprint 5.
- ❌ **`sold_count` & `rating_avg` di products** — diisi dari Order (Sprint 5) dan Review (Sprint 6). Default 0, update via queue job saat itu.

---

## Entities

| Tabel | Kolom Utama |
|---|---|
| `categories` | `id`, `parent_id`(nullable), `name`, `slug`(unique), `icon`, `level`(1-3), `sort_order`, `timestamps` |
| `products` | `id`, `store_id`, `category_id`, `name`, `slug`(unique), `description`, `status`, `min_price`(cents), `max_price`(cents), `total_stock`(default 0), `sold_count`(default 0), `rating_avg`(default 0), `weight_gram`, `timestamps` |
| `product_variants` | `id`, `product_id`, `sku`(unique), `price`(cents), `stock`, `weight_gram`, `attributes`(JSON), `timestamps` |
| `product_media` | `id`, `product_id`, `file`(storage key), `type`(image/video), `sort_order`, `is_primary`(default false), `timestamps` |

> **Catatan Kolom:**
> - `categories.icon` — simpan storage key, URL dihitung di Resource (bukan `icon_url`)
> - `product_media.file` — simpan storage key (bukan `url`). URL dihitung di Resource via `MediaService::publicUrl()`
> - `products.min_price` / `max_price` — denormalized dari variants, di-sync via `ProductVariantObserver`
> - `products.total_stock` — denormalized, di-sync via Observer saat variant stock berubah
> - `products.sold_count` / `rating_avg` — default 0, di-update via queue job dari modul Order/Review (bukan Sprint 4)
> - Harga selalu disimpan sebagai **integer cents** (Rp 50.000 = `5000000`)
> - `product_variants.attributes` — JSON key-value bebas: `{"warna": "Merah", "ukuran": "XL"}`
> - `Product` dan `ProductVariant` pakai `SoftDeletes` — merchant delete produk yang sudah punya Order history tidak boleh hilangkan data order

---

## Enums

```php
// app/Enums/ProductStatus.php
enum ProductStatus: string {
    case Draft    = 'draft';
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned'; // hanya Admin yang bisa set — merchant tidak bisa revert
}
```

---

## Middleware & Authorization

> ⚠️ `[auth:merchant]` **bukan** guard Sanctum yang valid. Gunakan middleware `merchant` (alias `EnsureMerchantOwnership`) yang sudah terdaftar di `bootstrap/app.php` sejak Sprint 3.

**ProductPolicy** wajib dibuat untuk cegah IDOR:
```php
// app/Policies/ProductPolicy.php
public function update(User $user, Product $product): bool
{
    return $product->store->user_id === $user->id;
}
public function delete(User $user, Product $product): bool
{
    return $product->store->user_id === $user->id;
}
public function manageMedia(User $user, Product $product): bool
{
    return $product->store->user_id === $user->id;
}
public function manageVariants(User $user, Product $product): bool
{
    return $product->store->user_id === $user->id;
}
```

> **Registrasi Policy:** Laravel 13 mendukung auto-discovery policy via naming convention (`Product` → `ProductPolicy`). Tidak perlu `AuthServiceProvider` terpisah — cukup pastikan file ada di `app/Policies/` dan nama sesuai konvensi. Jika perlu manual binding, tambahkan ke `AppServiceProvider::boot()`:
> ```php
> Gate::policy(Product::class, ProductPolicy::class);
> ```

---

## Routes

```
# ── Public ──────────────────────────────────────────────────────────────────
GET  /api/categories                                    [public]          # nested tree
GET  /api/categories/{slug}                             [public]          # single category + children
GET  /api/products                                      [public]          # listing, filter, paginate
GET  /api/products/{slug}                               [public]          # detail
GET  /api/products/{slug}/variants                      [public]          # daftar variant
GET  /api/stores/{slug}/products                        [public]          # ← implements 501 stub

# ── Merchant ─────────────────────────────────────────────────────────────────
GET    /api/merchant/products                           [auth:sanctum, merchant]
POST   /api/merchant/products                           [auth:sanctum, merchant]
GET    /api/merchant/products/{product}                 [auth:sanctum, merchant]
PUT    /api/merchant/products/{product}                 [auth:sanctum, merchant]
DELETE /api/merchant/products/{product}                 [auth:sanctum, merchant]
PUT    /api/merchant/products/{product}/status          [auth:sanctum, merchant]

# Media (presigned URL flow)
POST   /api/merchant/products/{product}/media           [auth:sanctum, merchant]  → generate URL
POST   /api/merchant/products/{product}/media/confirm   [auth:sanctum, merchant]  → confirm + save
DELETE /api/merchant/products/{product}/media/{media}   [auth:sanctum, merchant]  → delete R2 + DB
PUT    /api/merchant/products/{product}/media/reorder   [auth:sanctum, merchant]  → update sort_order

# Variants (CRUD individual — bukan bulk replace)
POST   /api/merchant/products/{product}/variants        [auth:sanctum, merchant]  → tambah variant
PUT    /api/merchant/products/{product}/variants/{variant} [auth:sanctum, merchant]
DELETE /api/merchant/products/{product}/variants/{variant} [auth:sanctum, merchant]

# ── Admin — Category Management ──────────────────────────────────────────────
POST   /api/admin/categories                            [auth:sanctum, admin]     → create (level auto-computed)
PUT    /api/admin/categories/{slug}                     [auth:sanctum, admin]     → update
DELETE /api/admin/categories/{slug}                     [auth:sanctum, admin]     → 422 if has children/products, 204 on success
```

> **Route Model Binding:**
> - `{product}` resolve via **slug** — tambahkan `getRouteKeyName(): string { return 'slug'; }` di `Product` model
> - `{variant}` resolve via **id** (default)
> - `GET /api/stores/{slug}/products` — **di-implement di `PublicStoreController`** (update existing, bukan route baru di product.php). Delegate ke `ProductService::getStoreProducts()`.
> - Route ini **tidak** perlu didefinisikan ulang di `routes/api/product.php` karena sudah terdaftar di `routes/api/merchant.php` sebagai bagian dari public store routes.

---

## Files to Create

```
# Enums
app/Enums/ProductStatus.php

# DTOs
app/DTOs/Product/CreateProductDTO.php
app/DTOs/Product/UpdateProductDTO.php

# Models
app/Models/Category.php
app/Models/Product.php
app/Models/ProductVariant.php
app/Models/ProductMedia.php

# Observers
app/Observers/ProductVariantObserver.php   # sync total_stock, min_price, max_price ke products

# Policies
app/Policies/ProductPolicy.php

# Services
app/Services/Product/ProductService.php
app/Services/Product/CategoryService.php

# Controllers
app/Http/Controllers/Api/Product/ProductController.php
app/Http/Controllers/Api/Product/CategoryController.php
app/Http/Controllers/Api/Product/ProductMediaController.php
app/Http/Controllers/Api/Product/ProductVariantController.php

# Enums / Middleware
app/Enums/UserRole.php                                         # buyer|merchant|admin (string-backed)
app/Http/Middleware/EnsureAdminRole.php                        # alias 'admin' — checks user->isAdmin()

# Form Requests
app/Http/Requests/Product/StoreProductRequest.php
app/Http/Requests/Product/UpdateProductRequest.php
app/Http/Requests/Product/UpdateProductStatusRequest.php
app/Http/Requests/Product/StoreVariantRequest.php
app/Http/Requests/Product/UpdateVariantRequest.php
app/Http/Requests/Product/StoreProductMediaRequest.php         # filename + mime
app/Http/Requests/Product/ConfirmProductMediaRequest.php       # key
app/Http/Requests/Product/ReorderProductMediaRequest.php       # array of {id, sort_order}
app/Http/Requests/Product/StoreCategoryRequest.php             # name, slug (regex /^[a-z0-9-]+$/), parent_id, icon
app/Http/Requests/Product/UpdateCategoryRequest.php            # all sometimes, slug unique ignoring current

# Resources
app/Http/Resources/Product/ProductResource.php                 # detail view
app/Http/Resources/Product/ProductListResource.php             # listing card (lighter)
app/Http/Resources/Product/CategoryResource.php                # dengan children (recursive)
app/Http/Resources/Product/VariantResource.php
app/Http/Resources/Product/ProductMediaResource.php

# Migrations
database/migrations/xxxx_create_categories_table.php
database/migrations/xxxx_create_products_table.php
database/migrations/xxxx_create_product_variants_table.php
database/migrations/xxxx_create_product_media_table.php

# Factories
database/factories/CategoryFactory.php
database/factories/ProductFactory.php
database/factories/ProductVariantFactory.php
database/factories/ProductMediaFactory.php

# Routes
routes/api/product.php

# Tests
tests/Feature/Api/Product/ProductTest.php
tests/Feature/Api/Product/CategoryTest.php
tests/Unit/Services/Product/ProductServiceTest.php
tests/Unit/Services/Product/CategoryServiceTest.php
```

---

## Shared Services Needed

| Service | Interface | Kegunaan |
|---|---|---|
| `MediaService` | `MediaServiceInterface` | Upload foto/video produk (presigned URL flow) |
| `CacheService` | `CacheServiceInterface` | Cache category tree (TTL 3600s), product list (TTL 300s) |

Cache keys:
- Category tree: `category:tree`
- Category by slug: `category:slug:{slug}`
- Product detail: `product:detail:{slug}`
- Store products: `store:products:{slug}:page:{page}`

---

## Business Logic Notes

### SoftDeletes pada Product dan ProductVariant
- `Product` dan `ProductVariant` **wajib** pakai trait `SoftDeletes`
- Alasan: merchant yang delete produk tidak boleh merusak data order yang sudah ada (Sprint 5+ butuh referensi ke `product_variants.id`)
- `ProductMedia` di-hard-delete (hapus file dari R2 + hapus record) karena tidak ada referensi dari luar
- Merchant yang soft-delete produk → produk tidak muncul di public listing, tapi data order tetap valid

### Product Slug
- Auto-generate dari nama produk: `Str::slug($name)`, loop suffix `-2`, `-3` jika collision
- Slug **tidak berubah** setelah produk aktif
- `Product::getRouteKeyName()` return `'slug'` agar Route Model Binding bekerja via slug

### Product Status Lifecycle
```
draft → active      (oleh merchant, manual)
active → inactive   (oleh merchant, manual)
inactive → active   (oleh merchant, manual)
any → banned        (hanya Admin — Sprint P2)
banned → *          (DILARANG — merchant tidak bisa revert dari banned)
```
Validasi di `UpdateProductStatusRequest` + service guard.

### Denormalized Counters via Observer
`ProductVariantObserver` di-boot di `AppServiceProvider`:
```php
// Trigger: created, updated, deleted pada ProductVariant
// Action: hitung ulang min_price, max_price, total_stock dari semua variant aktif
// Update: products table via DB::table()->where('id', $variant->product_id)->update(...)
```

### Harga Produk
- Semua harga dalam **integer cents**: Rp 50.000 → `5000000`
- Resource wajib expose dua field:
```php
'price_cents' => $this->price,
'price'       => 'Rp ' . number_format($this->price / 100, 0, ',', '.'),
```

### Public Product Listing
Filter yang didukung via query string:
- `?search=` — full text search pada name + description
- `?category=` — slug kategori (termasuk **semua sub-kategori** di bawahnya)
- `?min_price=` / `?max_price=` — dalam rupiah (konversi ke cents di service)
- `?sort=` — `price_asc`, `price_desc`, `newest`, `popular` (sold_count)
- `?store=` — slug toko

Pagination: 20 per halaman, response ikut standard `paginationMeta`.

> **Filter by category include sub-kategori:** Implementasi via `CategoryService::getDescendantIds(Category $cat): array` — return semua ID category (termasuk dirinya sendiri dan seluruh keturunannya). Kemudian query `whereIn('category_id', $ids)`. Ini menghindari query N+1 untuk tree traversal.

### Category Tree
Response `GET /api/categories` adalah nested tree:
```json
[
  {
    "id": 1, "name": "Elektronik", "slug": "elektronik", "level": 1,
    "children": [
      {
        "id": 2, "name": "Handphone", "slug": "handphone", "level": 2,
        "children": [
          { "id": 3, "name": "Android", "slug": "android", "level": 3, "children": [] }
        ]
      }
    ]
  }
]
```
Implementasi: query semua categories sekaligus, build tree di PHP (hindari N+1). Cache TTL 3600s.

### Product Media Upload Flow
Sama persis dengan logo/banner (Sprint 3):
1. `POST .../media` → `MediaService::generatePresignedUrl('products/{id}', filename, mime)`
2. Client PUT ke R2
3. `POST .../media/confirm` → `confirmUpload(key)`, insert `product_media`, set `is_primary = true` jika media pertama
4. `DELETE .../media/{mediaId}` → `media->delete(file)`, soft-delete atau hard-delete record
5. `PUT .../media/reorder` → bulk update `sort_order`

### Variant Attributes (JSON)
Sprint 4 pakai JSON bebas, bukan tabel relasi:
```json
{ "warna": "Merah", "ukuran": "XL", "material": "Cotton" }
```
Format baku ditentukan di `StoreVariantRequest` rules — tidak ada validasi strict terhadap key, cukup `array`.

### Reorder Media — IDOR Protection
`PUT /api/merchant/products/{product}/media/reorder` menerima array `[{id, sort_order}]`. Service wajib validasi bahwa semua `media_id` yang dikirim **benar-benar milik product tersebut** sebelum update:
```php
// Di ProductService::reorderMedia():
$validIds = ProductMedia::where('product_id', $product->id)->pluck('id')->toArray();
foreach ($items as $item) {
    if (! in_array($item['id'], $validIds)) {
        throw new \Illuminate\Auth\Access\AuthorizationException();
    }
}
```

### `stores/{slug}/products` Implementation
Update `PublicStoreController::products()` — saat ini return 501. Sprint 4 delegate ke `ProductService::getStoreProducts(Store $store, array $filters): LengthAwarePaginator`. Tidak perlu route baru, cukup update method yang sudah ada.

---

## Test Scenarios (Minimum)

### CategoryTest (Feature)
- `test_public_can_get_category_tree`
- `test_category_tree_is_nested`
- `test_category_not_found_returns_404`

### ProductTest (Feature)
- `test_merchant_can_create_product`
- `test_non_merchant_cannot_create_product`
- `test_merchant_cannot_edit_other_merchants_product` (IDOR)
- `test_product_slug_is_auto_generated`
- `test_public_can_list_products`
- `test_public_can_filter_products_by_category`
- `test_public_can_view_product_detail`
- `test_merchant_can_upload_product_media`
- `test_delete_media_removes_file_from_r2`
- `test_merchant_can_add_variant`
- `test_merchant_can_update_variant`
- `test_total_stock_synced_after_variant_update`
- `test_min_max_price_synced_after_variant_change`
- `test_merchant_can_update_product_status_to_active`
- `test_merchant_cannot_set_banned_status`
- `test_store_products_public_endpoint_returns_paginated_list`

### ProductServiceTest (Unit)
- `test_generate_unique_slug_adds_suffix_on_collision`
- `test_cannot_transition_to_banned_status`
- `test_create_product_sets_correct_defaults`

### CategoryServiceTest (Unit)
- `test_build_tree_nests_children_correctly`
- `test_category_cache_is_invalidated_on_update`

---

## Checklist Eksekusi

- [x] Buat `ProductStatus` enum
- [x] Buat migrations (categories, products + SoftDeletes, product_variants + SoftDeletes, product_media)
- [x] Buat Models + Factories — `Product::getRouteKeyName()` return `'slug'`
- [x] Buat `ProductVariantObserver` + boot di `AppServiceProvider::boot()`
- [x] Buat `ProductPolicy` (auto-discovery via naming convention, tidak perlu `AuthServiceProvider`)
- [x] Buat DTOs: `CreateProductDTO`, `UpdateProductDTO`
- [x] Buat `CategoryService` dengan `getTree()`, `findBySlug()`, `getDescendantIds()`
- [x] Buat `ProductService` dengan semua business logic + IDOR guard di `reorderMedia()`
- [x] Buat Controllers (Category, Product, ProductMedia, ProductVariant)
- [x] Buat FormRequests (8 total)
- [x] Buat Resources (ProductResource, ProductListResource, CategoryResource, VariantResource, ProductMediaResource)
- [x] Buat routes/api/product.php
- [x] Update `PublicStoreController::products()` — ganti 501 stub dengan implementasi nyata
- [x] Buat Feature Tests + Unit Tests
- [x] Buat `UserRole` enum + `role` column migration + `EnsureAdminRole` middleware + register di `bootstrap/app.php`
- [x] Buat `StoreCategoryRequest`, `UpdateCategoryRequest`
- [x] Tambah admin category CRUD ke `CategoryController` (`store`, `update`, `destroy`)
- [x] Tambah admin routes ke `routes/api/product.php`
- [x] Tambah `admin()` factory state ke `UserFactory`
- [x] Tambah 8 admin category tests ke `CategoryTest`
- [x] Update Postman collection (folder "05. Product")
- [x] Update planning/04-product.md → Status ✅ Selesai
