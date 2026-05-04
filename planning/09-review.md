# MODULE 9 — Review & Rating
**Priority:** 🟡 P2 | **Status:** ⬜ Belum | **Sprint:** 9

---

## Yang Perlu Dibangun
- ⬜ Product review (bintang 1-5, komentar, foto/video)
- ⬜ Review gate — hanya bisa review setelah order berstatus `delivered`/`completed`
- ⬜ Satu user hanya bisa review satu kali per order item
- ⬜ Merchant reply to review
- ⬜ Review helpfulness vote (thumbs up)
- ⬜ Review moderation (admin approve/reject)
- ⬜ Store rating auto-aggregation
- ⬜ Product rating denormalization (`rating_avg`, `rating_count` di tabel `products`)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `reviews` | `order_item_id`, `user_id`, `product_id`, `store_id`, `rating` (1-5), `comment`, `status` (pending/approved/rejected), `is_anonymous` |
| `review_media` | `review_id`, `url`, `type` (image/video), `sort_order` |
| `review_replies` | `review_id`, `store_id`, `content`, `replied_at` |
| `review_votes` | `review_id`, `user_id`, `is_helpful` (boolean) |

---

## Routes
```
# Public
GET  /api/products/{id}/reviews                [public] ?rating=&sort=newest
GET  /api/stores/{slug}/reviews                [public]

# Buyer
POST /api/reviews                              [auth]
PUT  /api/reviews/{id}                         [auth] (edit dalam 24 jam)

# Merchant
POST /api/reviews/{id}/replies                 [auth:merchant]

# Interaction
POST /api/reviews/{id}/votes                   [auth]

# Admin
PUT  /api/admin/reviews/{id}/status            [auth:admin]
```

---

## Files to Create
```
app/Http/Controllers/Api/Review/ReviewController.php
app/Http/Requests/Review/StoreReviewRequest.php
app/Http/Requests/Review/StoreReplyRequest.php
app/Http/Resources/Review/ReviewResource.php
app/Http/Resources/Review/ReviewListResource.php
app/Services/Review/ReviewService.php
app/Models/Review.php
app/Models/ReviewMedia.php
app/Models/ReviewReply.php
app/Models/ReviewVote.php
database/migrations/xxxx_create_reviews_table.php
database/migrations/xxxx_create_review_media_table.php
database/migrations/xxxx_create_review_replies_table.php
database/migrations/xxxx_create_review_votes_table.php
routes/api/review.php
tests/Feature/Api/Review/ReviewTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `MediaService` | Upload foto/video review ke S3 |
| `NotificationService` | Notif merchant ada review baru |

---

## Business Logic Notes
- Gate: cek `order_item_id` milik user dan order statusnya sudah `delivered`/`completed`
- Duplikasi: unique constraint pada `(order_item_id, user_id)` di DB
- Rating aggregation: update `rating_avg` dan `rating_count` di tabel `products` via Observer atau Queue Job setelah review diapprove
- Store rating: average dari semua `rating_avg` produk toko tersebut
- Edit window: review bisa diedit dalam 24 jam pertama setelah dibuat
- Default review status: `approved` (auto-approve), kecuali ada laporan → masuk `pending`
