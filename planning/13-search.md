# MODULE 13 — Search & Discovery
**Priority:** 🟢 P3 | **Status:** ⬜ Belum | **Sprint:** 13

---

## Yang Perlu Dibangun
- ⬜ Full-text search produk (Laravel Scout + Meilisearch)
- ⬜ Filter: harga, kategori, rating, kota penjual, kurir tersedia
- ⬜ Sorting: relevansi, harga termurah/termahal, terlaris, terbaru, rating tertinggi
- ⬜ Search autocomplete (Redis-cached, top 10 saran)
- ⬜ Search history per user (simpan query terakhir)
- ⬜ Trending products (based on view count + sold count)
- ⬜ "Mungkin kamu suka" — rekomendasi berdasarkan history + kategori yang sering dilihat

---

## Dependencies
```bash
composer require laravel/scout
composer require meilisearch/meilisearch-php
```

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=your_master_key
```

`docker-compose.yml` — tambahkan service:
```yaml
meilisearch:
    image: getmeili/meilisearch:latest
    container_name: laravel-meilisearch
    ports:
        - "7700:7700"
    environment:
        - MEILI_MASTER_KEY=${MEILISEARCH_KEY}
    volumes:
        - ./docker/meilisearch/data:/meili_data
    networks:
        - laravel
```

---

## Entities (Search Indexes, bukan DB tables)

### Meilisearch Index: `products`
```json
{
    "id": 1,
    "name": "iPhone 15 Pro",
    "slug": "iphone-15-pro",
    "category_id": 5,
    "category_name": "Smartphone",
    "store_id": 12,
    "store_name": "iStore Official",
    "store_city": "Jakarta",
    "min_price": 15000000,
    "max_price": 19000000,
    "rating_avg": 4.8,
    "rating_count": 234,
    "sold_count": 1250,
    "status": "active",
    "created_at": "2026-01-15"
}
```

### Redis Keys (untuk autocomplete & trending)
```
search:autocomplete:{prefix}     → sorted set (TTL: 1800s)
search:trending                  → sorted set by score (TTL: 3600s)
search:history:{user_id}         → list LIFO, max 20 items
```

---

## Routes
```
GET /api/search                                [public] ?q=&category=&min_price=&max_price=&sort=&city=
GET /api/search/autocomplete                   [public] ?q=
GET /api/search/trending                       [public]
GET /api/search/history                        [auth]
DELETE /api/search/history                     [auth] (clear all)
DELETE /api/search/history/{query}             [auth] (hapus satu)
GET /api/products/{id}/recommendations         [public]
```

---

## Files to Create
```
app/Http/Controllers/Api/Search/SearchController.php
app/Http/Resources/Search/SearchResultResource.php
app/Services/Search/SearchService.php
app/Services/Search/RecommendationService.php
# Scout Integration
app/Models/Product.php                         (add Searchable trait)
database/seeders/SearchIndexSeeder.php
# Artisan Command
app/Console/Commands/IndexProductsCommand.php  (php artisan products:index)
routes/api/search.php (atau merge ke product.php)
tests/Feature/Api/Search/SearchTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `CacheService` | Cache autocomplete & trending (Redis sorted set) |

---

## Business Logic Notes
- Scout `Searchable` pada `Product` — override `toSearchableArray()` dengan data denormalized
- Re-index saat produk create/update/delete: otomatis via Scout Observer
- Autocomplete: ambil dari Redis jika ada, fallback ke Meilisearch jika miss
- Trending: score = `(view_count × 0.3) + (sold_count × 0.7)`, diupdate via scheduled job setiap jam
- Rekomendasi sederhana: ambil produk dari kategori yang sama, sort by rating + sold
- Full rekomendasi (P4): collaborative filtering atau machine learning (di luar scope saat ini)
- `SCOUT_DRIVER=null` untuk environment testing agar test tidak butuh Meilisearch
