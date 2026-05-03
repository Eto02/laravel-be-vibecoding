---
description: Scaffold a Service class with constructor DI and a matching PHPUnit unit test stub
---

Create a Service class for: $ARGUMENTS

Treat the argument as `{Name}` in PascalCase. If the argument includes "implementing" or "interface" keywords, also generate an interface and AppServiceProvider binding.

---

## File 1: Service Class

Path: `app/Services/{Name}Service.php`

```php
<?php

namespace App\Services;

class {Name}Service
{
    public function __construct(
        // Inject dependencies here via constructor only.
        // Examples:
        // private readonly AnotherService $anotherService,
        // private readonly \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    // Public methods below — each with a single clear responsibility.
    // Always use typed parameters and return types.
    // Do NOT call response()->json() here — return data, let controllers return responses.
    // Do NOT use app() or resolve() inside this class.
    // Let ModelNotFoundException and ValidationException bubble up — do not catch them here
    // unless you are re-throwing a domain-specific exception.
}
```

---

## File 2 (conditional): Interface

Only generate if the argument mentions "interface" or "implementing". Path: `app/Contracts/{Name}Interface.php`

```php
<?php

namespace App\Contracts;

interface {Name}Interface
{
    // Define the public contract here — match the Service's public methods
}
```

And add to `app/Providers/AppServiceProvider.php` in the `register()` method:

```php
$this->app->bind(
    \App\Contracts\{Name}Interface::class,
    \App\Services\{Name}Service::class,
);
```

---

## File 3: Unit Test

Path: `tests/Unit/Services/{Name}ServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Services\{Name}Service;
use PHPUnit\Framework\TestCase;

class {Name}ServiceTest extends TestCase
{
    private {Name}Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Instantiate with mocked dependencies:
        // $this->service = new {Name}Service(
        //     $this->createMock(DependencyClass::class),
        // );
        $this->service = new {Name}Service();
    }

    public function test_example(): void
    {
        // Replace with real assertion for the most critical method
        $this->assertInstanceOf({Name}Service::class, $this->service);
    }
}
```

---

## Rules

- Constructor injection only — no `app()`, `resolve()`, or static facades accessed inside methods (inject the contract instead)
- Return typed values — avoid `mixed` or untyped returns
- Services return data (arrays, models, collections, paginated results) — never `JsonResponse`
- Fire events via injected `Dispatcher` contract, not the `Event` facade
- Keep methods focused — if a method exceeds ~20 lines, consider splitting it
