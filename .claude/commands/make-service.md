---
description: Scaffold a Service class within a domain folder, with constructor DI and a matching PHPUnit unit test stub. Always uses the domain-namespaced path per CLAUDE.md.
---

Create a Service class for: $ARGUMENTS

Format expected: `{Domain}/{FeatureName}` — e.g. `Order/OrderService`, `Shared/EmailService`, `Product/CategoryService`

If `Shared/` is the domain, also run `make-shared-service` instead for full interface scaffolding.

---

## File 1: Service Class

Path: `app/Services/{Domain}/{Name}Service.php`

Namespace: `App\Services\{Domain}`

```php
<?php

namespace App\Services\{Domain};

class {Name}Service
{
    public function __construct(
        // Inject dependencies via constructor only.
        // Domain services: inject other domain/shared services
        // private readonly \App\Services\Shared\EmailService $email,
        // private readonly \App\Services\Shared\NotificationService $notification,
        // Shared services: inject Laravel contracts
        // private readonly \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    // Public methods — each with a single clear responsibility.
    // Always use typed parameters and return types.
    // Return data (arrays, models, collections) — never JsonResponse.
    // Do NOT use app(), resolve(), or static facades accessed inside methods.
    // Let ModelNotFoundException and ValidationException bubble up naturally.
    // Fire events via injected Dispatcher, not the Event facade.
}
```

---

## File 2 (conditional): Interface

Only generate if the argument mentions "interface" or "implementing", OR if domain is `Shared/`, `Payment/Gateways/`, or `Shipping/Providers/`.

Path: `app/Contracts/{Domain}/{Name}Interface.php`

Namespace: `App\Contracts\{Domain}`

```php
<?php

namespace App\Contracts\{Domain};

interface {Name}Interface
{
    // Define the public contract here — match the Service's public methods
}
```

Add to `app/Providers/AppServiceProvider.php` in `register()`:
```php
$this->app->bind(
    \App\Contracts\{Domain}\{Name}Interface::class,
    \App\Services\{Domain}\{Name}Service::class,
);
```

---

## File 3: Unit Test

Path: `tests/Unit/Services/{Domain}/{Name}ServiceTest.php`

Namespace: `Tests\Unit\Services\{Domain}`

```php
<?php

namespace Tests\Unit\Services\{Domain};

use App\Services\{Domain}\{Name}Service;
use PHPUnit\Framework\TestCase;

class {Name}ServiceTest extends TestCase
{
    private {Name}Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock all dependencies:
        // $mockEmail = $this->createMock(\App\Services\Shared\EmailService::class);
        // $this->service = new {Name}Service($mockEmail);
        $this->service = new {Name}Service();
    }

    public function test_service_instantiates_correctly(): void
    {
        $this->assertInstanceOf({Name}Service::class, $this->service);
    }
}
```

---

## Rules

- Constructor injection only — no `app()`, `resolve()`, or static facades inside methods
- Return typed values — avoid `mixed` or untyped returns
- Services return data — never `JsonResponse`
- Fire events via injected `Dispatcher` contract, not the `Event` facade
- Keep methods focused — if a method exceeds ~25 lines, consider splitting it
- **Shared services** (`app/Services/Shared/`) must always have a matching Interface in `app/Contracts/Shared/`
- **Domain services** only need an interface if they are swappable providers (Payment, Shipping)
