# MODULE 4 — Product Catalog
**Priority:** 🟠 P1 | **Status:** ⬜ Belum | **Sprint:** 4

---

## Yang Perlu Dibangun
- ⬜ Category Tree — hirarkis max 3 level (Elektronik → Handphone → Android)
- ⬜ Product CRUD — merchant only, dengan status lifecycle
- ⬜ Product Variants — kombinasi atribut (Warna × Ukuran)
- ⬜ Product Attributes — key-value fleksibel per kategori
- ⬜ Product Media — upload multiple foto, urutan bisa diatur
- ⬜ Inventory per variant — stok, SKU, berat, dimensi
- ⬜ Product Status: `draft → active → inactive → banned`
- ⬜ Public listing dengan filter & sorting
- ⬜ Merchant product management (semua status)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `categories` | `parent_id`, `name`, `slug`, `icon_url`, `level`, `sort_order` |
| `products` | `store_id`, `category_id`, `name`, `slug`, `description`, `status`, `min_price`, `max_price`, `total_stock`, `sold_count`, `rating_avg` |
| `product_variants` | `product_id`, `sku`, `price`, `stock`, `weight_gram`, `attributes` (JSON) |
| `product_media` | `product_id`, `url`, `type` (image/video), `sort_order`, `is_primary` |
| `product_attributes` | `category_id`, `name`, `type` (color/size/text) |
| `attribute_values` | `attribute_id`, `value`, `hex_color` (untuk tipe color) |

---

## Enums
```php
enum ProductStatus: string {
    case Draft    = 'draft';
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';
}
```

---

## Routes
```
# Public
GET /api/categories                            [public]
GET /api/categories/{slug}                     [public]
GET /api/products                              [public] ?search=&category=&min_price=&max_price=&sort=price_asc
GET /api/products/{id}                         [public]
GET /api/products/{id}/variants                [public]

# Merchant
GET  /api/merchant/products                    [auth:merchant]
POST /api/merchant/products                    [auth:merchant]
GET  /api/merchant/products/{id}               [auth:merchant]
PUT  /api/merchant/products/{id}               [auth:merchant]
DELETE /api/merchant/products/{id}             [auth:merchant]
POST /api/merchant/products/{id}/media         [auth:merchant]
DELETE /api/merchant/products/{id}/media/{mediaId} [auth:merchant]
PUT  /api/merchant/products/{id}/variants      [auth:merchant]
PUT  /api/merchant/products/{id}/status        [auth:merchant]
```

---

## Files to Create
```
app/Http/Controllers/Api/Product/ProductController.php
app/Http/Controllers/Api/Product/CategoryController.php
app/Http/Requests/Product/StoreProductRequest.php
app/Http/Requests/Product/UpdateProductRequest.php
app/Http/Requests/Product/StoreProductMediaRequest.php
app/Http/Resources/Product/ProductResource.php
app/Http/Resources/Product/ProductListResource.php
app/Http/Resources/Product/CategoryResource.php
app/Http/Resources/Product/VariantResource.php
app/Services/Product/ProductService.php
app/Services/Product/CategoryService.php
app/Models/Category.php
app/Models/Product.php
app/Models/ProductVariant.php
app/Models/ProductMedia.php
app/Enums/ProductStatus.php
database/migrations/xxxx_create_categories_table.php
database/migrations/xxxx_create_products_table.php
database/migrations/xxxx_create_product_variants_table.php
database/migrations/xxxx_create_product_media_table.php
routes/api/product.php
tests/Feature/Api/Product/ProductTest.php
tests/Feature/Api/Product/CategoryTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `MediaService` | Upload foto/video produk ke S3 |
| `CacheService` | Cache category tree (3600s), product list (300s) |

---

## Business Logic Notes
- `min_price` dan `max_price` di-denormalize dari variants untuk performance query filter
- `total_stock` di-denormalize, di-update via Observer saat variant stock berubah
- Inventory: jika `total_stock = 0` → otomatis set status `inactive` (optional, konfigurasi)
- Harga disimpan dalam **integer cents** (Rp 50.000 = `5000000`)
- Kategori tree di-cache di Redis, di-bust saat admin update kategori
- Produk yang dibanned oleh admin tidak bisa diaktifkan kembali oleh merchant
