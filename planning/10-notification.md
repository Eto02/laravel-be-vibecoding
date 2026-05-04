# MODULE 10 — Notification System
**Priority:** 🟡 P2 | **Status:** ⬜ Belum | **Sprint:** 10

---

## Yang Perlu Dibangun
- ⬜ In-app notification (bell icon, list, mark as read, mark all read)
- ⬜ Push notification via FCM (Firebase Cloud Messaging)
- ⬜ Email notification (sudah partial via EmailService)
- ⬜ WhatsApp notification via Fonnte/Meta API
- ⬜ Notification preferences (user pilih channel yang aktif)
- ⬜ Device push token registration

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `notifications` | `user_id`, `type`, `title`, `body`, `data` (JSON), `channel`, `read_at`, `action_url` |
| `notification_preferences` | `user_id`, `channel` (email/push/whatsapp), `type` (order/payment/promo), `is_enabled` |
| `push_tokens` | `user_id`, `device_id`, `platform` (ios/android/web), `token`, `is_active`, `last_used_at` |

---

## Routes
```
GET    /api/notifications                      [auth]
GET    /api/notifications/unread-count         [auth]
PUT    /api/notifications/{id}/read            [auth]
PUT    /api/notifications/read-all             [auth]
DELETE /api/notifications/{id}                 [auth]
DELETE /api/notifications                      [auth] (clear all)

POST   /api/notifications/push-tokens          [auth]
DELETE /api/notifications/push-tokens/{deviceId} [auth]

GET    /api/notifications/preferences          [auth]
PUT    /api/notifications/preferences          [auth]
```

---

## Shared Services (Core modul ini)

### NotificationService (Orchestrator)
```php
interface NotificationServiceInterface {
    // Kirim ke semua channel yang diaktifkan user
    public function notify(User $user, string $type, array $data): void;
    // Kirim ke channel spesifik
    public function notifyVia(User $user, string $channel, string $type, array $data): void;
}
```

### PushNotificationService (FCM)
```php
interface PushNotificationServiceInterface {
    public function sendToUser(User $user, string $title, string $body, array $data = []): void;
    public function sendToDevice(string $token, string $title, string $body, array $data = []): void;
    public function sendToMultiple(array $tokens, string $title, string $body): void;
}
```

### SmsService (WA/SMS)
```php
interface SmsServiceInterface {
    public function send(string $phone, string $message): void;
    public function sendWhatsApp(string $phone, string $message): void;
}
```

---

## Files to Create
```
app/Http/Controllers/Api/Notification/NotificationController.php
app/Http/Requests/Notification/UpdatePreferenceRequest.php
app/Http/Requests/Notification/RegisterPushTokenRequest.php
app/Http/Resources/Notification/NotificationResource.php
app/Services/Notification/NotificationService.php
app/Contracts/Shared/NotificationServiceInterface.php
app/Services/Shared/PushNotificationService.php
app/Contracts/Shared/PushNotificationServiceInterface.php
app/Services/Shared/SmsService.php
app/Contracts/Shared/SmsServiceInterface.php
app/Models/Notification.php
app/Models/NotificationPreference.php
app/Models/PushToken.php
database/migrations/xxxx_create_notifications_table.php
database/migrations/xxxx_create_notification_preferences_table.php
database/migrations/xxxx_create_push_tokens_table.php
routes/api/notification.php
tests/Feature/Api/Notification/NotificationTest.php
tests/Unit/Services/Shared/PushNotificationServiceTest.php
tests/Unit/Services/Shared/SmsServiceTest.php
```

---

## Business Logic Notes
- Notification type list: `order_placed`, `order_paid`, `order_shipped`, `order_delivered`, `payment_success`, `payment_failed`, `review_received`, `promo_flash_sale`, `price_drop`
- In-app: disimpan di DB, di-query saat user buka bell icon
- Push: dispatch via Queue Job `SendPushNotificationJob` agar tidak blocking
- WA: Fonnte API (provider default) — bisa swap ke Twilio via interface
- Preferences: default semua channel `enabled = true` saat user register
- Invalid FCM token: tangkap error dari FCM response, set `is_active = false`
