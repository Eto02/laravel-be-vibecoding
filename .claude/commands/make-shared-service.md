---
description: Scaffold a Shared Service — a cross-cutting service that can be injected into any domain service. Used for Email, SMS, Push Notification, OTP, File Upload, etc.
---

Create a Shared Service for: $ARGUMENTS

Shared Services live in `app/Services/Shared/` and are injected into domain services via constructor DI. They must be bound in `AppServiceProvider` if interface-based.

Format: `{Name}` in PascalCase — e.g. `Email`, `Sms`, `PushNotification`, `Otp`, `Media`

---

## File 1: Interface (always required for Shared Services)

Path: `app/Contracts/Shared/{Name}ServiceInterface.php`

Namespace: `App\Contracts\Shared`

```php
<?php

namespace App\Contracts\Shared;

interface {Name}ServiceInterface
{
    // Define the public contract — all implementations must satisfy this
}
```

---

## File 2: Concrete Service Implementation

Path: `app/Services/Shared/{Name}Service.php`

Namespace: `App\Services\Shared`

```php
<?php

namespace App\Services\Shared;

use App\Contracts\Shared\{Name}ServiceInterface;

class {Name}Service implements {Name}ServiceInterface
{
    public function __construct(
        // Inject Laravel contracts or third-party clients — no facades inside methods
        // private readonly \Illuminate\Mail\MailManager $mailer,
        // private readonly \Illuminate\Redis\RedisManager $redis,
    ) {}

    // Implement all interface methods here
    // Return typed values — avoid mixed
    // Throw domain exceptions for recoverable errors (not Http exceptions)
    // Let unexpected exceptions bubble up naturally
}
```

---

## File 3: AppServiceProvider Binding

Add to `register()` method in `app/Providers/AppServiceProvider.php`:

```php
$this->app->bind(
    \App\Contracts\Shared\{Name}ServiceInterface::class,
    \App\Services\Shared\{Name}Service::class,
);
```

---

## File 4: Unit Test

Path: `tests/Unit/Services/Shared/{Name}ServiceTest.php`

Namespace: `Tests\Unit\Services\Shared`

```php
<?php

namespace Tests\Unit\Services\Shared;

use App\Services\Shared\{Name}Service;
use PHPUnit\Framework\TestCase;

class {Name}ServiceTest extends TestCase
{
    private {Name}Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock dependencies:
        // $mockMailer = $this->createMock(\Illuminate\Mail\MailManager::class);
        // $this->service = new {Name}Service($mockMailer);
        $this->service = new {Name}Service();
    }

    public function test_service_instantiates_correctly(): void
    {
        $this->assertInstanceOf({Name}Service::class, $this->service);
    }
}
```

---

## Usage in Domain Services

After creating, inject via constructor in any domain service:

```php
// In any domain service e.g. app/Services/Order/OrderService.php
use App\Contracts\Shared\{Name}ServiceInterface;

public function __construct(
    private readonly {Name}ServiceInterface ${name}Service,
) {}
```

---

## Standard Shared Services Reference

These are the pre-defined Shared Services for this project. Check if one already exists before creating a new one.

| Class | Interface | Purpose |
|---|---|---|
| `EmailService` | `EmailServiceInterface` | Send Mailables or raw emails via Laravel Mail |
| `SmsService` | `SmsServiceInterface` | Send SMS/WhatsApp via Fonnte or Twilio |
| `PushNotificationService` | `PushNotificationServiceInterface` | Send FCM push notifications |
| `OtpService` | `OtpServiceInterface` | Generate & verify OTP (Redis-backed, TTL 5min) |
| `MediaService` | `MediaServiceInterface` | Upload/delete files to S3/Minio/Cloudinary |
| `NotificationService` | `NotificationServiceInterface` | Orchestrator: picks channel based on user prefs |
| `CacheService` | `CacheServiceInterface` | Typed cache helpers with domain-aware TTL conventions |

---

## Rules for Shared Services

1. **No domain logic** — Shared Services must be domain-agnostic. They deal with infrastructure (email delivery, file upload), not business rules.
2. **Interface-first** — Always define the interface before the implementation. This allows swapping providers without touching domain code (e.g., swap Fonnte → Twilio).
3. **Constructor injection only** — No `app()`, `resolve()`, or static facades inside methods.
4. **Queue long operations** — If the operation is slow (email send, push, file upload), the service should dispatch a Job, not execute synchronously.
5. **One responsibility** — `EmailService` sends emails only. `NotificationService` orchestrates channels but delegates to `EmailService`, `SmsService`, and `PushNotificationService`.
