# MODULE 10 — Notification System
**Priority:** 🟡 P2 | **Status:** ⬜ Belum | **Sprint:** 10

---

## Arsitektur: Event-Driven

Sistem tidak menggunakan satu orchestrator tunggal. Setiap domain (Order, Payment, dll) memicu **Event**, dan **Listeners** di modul ini menangani pengiriman berdasarkan preferensi user.

---

## Yang Perlu Dibangun
- ⬜ In-app notification (Database-backed)
- ⬜ Push notification (FCM Integration)
- ⬜ Email notification (via EmailService)
- ⬜ WhatsApp notification (Fonnte/Meta API)
- ⬜ Notification preferences per user
- ⬜ Device push token registration

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `notifications` | `user_id`, `type`, `title`, `body`, `data` (JSON), `read_at`, `action_url` |
| `notification_preferences` | `user_id`, `channel` (email/push/whatsapp), `event_type` (order/payment/promo), `is_enabled` |
| `push_tokens` | `user_id`, `device_id`, `platform`, `token`, `is_active` |

---

## Flow Kerja
1. **Event Trigger:** `event(new OrderPlaced($order))`
2. **Listener:** `SendOrderNotifications` (Queued)
3. **Logic di Listener:**
   - Cek `notification_preferences` user.
   - Jika `email` enabled: Panggil `EmailService`.
   - Jika `push` enabled: Panggil `PushNotificationService`.
   - Jika `whatsapp` enabled: Panggil `SmsService`.
   - **Selalu** simpan record di tabel `notifications` (In-app).

---

## Routes
```
GET    /api/notifications                      [auth]
PUT    /api/notifications/{id}/read            [auth]
PUT    /api/notifications/read-all             [auth]
DELETE /api/notifications/{id}                 [auth]

POST   /api/notifications/push-tokens          [auth]
DELETE /api/notifications/push-tokens/{id}     [auth]

GET    /api/notifications/preferences          [auth]
PUT    /api/notifications/preferences          [auth]
```

---

## Shared Services (Low-level Delivery)

### PushNotificationService (FCM)
```php
interface PushNotificationServiceInterface {
    public function sendToUser(User $user, string $title, string $body, array $data = []): void;
}
```

### SmsService (WA/SMS)
```php
interface SmsServiceInterface {
    public function sendWhatsApp(string $phone, string $message): void;
}
```

---

## Files to Create
```
app/Http/Controllers/Api/Notification/NotificationController.php
app/Http/Controllers/Api/Notification/PreferenceController.php
app/Http/Resources/Notification/NotificationResource.php
app/Services/Shared/PushNotificationService.php
app/Services/Shared/SmsService.php
app/Models/Notification.php
app/Models/NotificationPreference.php
app/Models/PushToken.php
app/Listeners/SendOrderNotifications.php       (ShouldQueue)
app/Listeners/SendPaymentNotifications.php     (ShouldQueue)
```

---

## Business Logic Notes
- **Decoupling:** Modul asal tidak perlu tahu channel apa yang dipakai.
- **Idempotency:** Gunakan ID event sebagai referensi jika perlu mencegah notifikasi ganda.
- **Failover:** Jika salah satu channel gagal (misal WA provider down), channel lain (Email/Push) harus tetap jalan.
- **Cleaning:** Hapus notifikasi yang sudah dibaca > 30 hari via scheduled job.
