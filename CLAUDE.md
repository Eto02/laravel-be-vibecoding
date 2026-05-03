# Laravel 13 Marketplace API — Project Context

## Project Purpose

Headless REST API backend for a multi-vendor marketplace. Serves mobile and SPA clients. No Blade/frontend in this repo. All responses are JSON. API prefix is `/api` (handled automatically by Laravel 13's `routes/api.php` binding).

## Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.3, Laravel 13 |
| Auth | Laravel Sanctum (Bearer) + custom refresh token rotation + Laravel Socialite (OAuth) |
| Primary DB | MySQL 8.0 — relational/transactional data |
| Document DB | MongoDB — product catalogs, search indexes, activity logs (`mongodb/laravel-mongodb`) |
| Cache / Queue / Session | Redis (phpredis) |
| Email | Laravel Mail — SMTP/Resend/SES (driver via `MAIL_MAILER` env) |
| Payment | Stripe or Midtrans — interface-based in `app/Services/Payment/` |
| Testing | PHPUnit 12 |
| Containerization | Docker Compose (`docker-compose.yml` dev, `docker-compose.prod.yml` prod) |

## Directory Structure

```
app/
├── Http/
│   ├── Controllers/Api/        # HTTP layer — route, validate (via FormRequest), call Service, return response
│   ├── Requests/               # FormRequest classes — one per action (StoreProductRequest, UpdateProductRequest)
│   ├── Resources/              # API Resource classes — one per model, wrap ALL model output
│   └── Responses/              # ApiResponse static helper — all responses go through this
├── Models/                     # Eloquent models — fillable, casts, relationships, scopes only
├── Services/                   # Business logic — controllers call these, never call Eloquent directly from controllers
│   └── Payment/                # Payment gateway abstraction (PaymentGatewayInterface + implementations)
├── Enums/                      # PHP 8.1+ backed enums for status fields
├── Events/                     # Laravel events (e.g. OrderPlaced)
├── Listeners/                  # Event listeners (e.g. SendOrderConfirmationEmail)
├── Mail/                       # Mailable classes — all implement ShouldQueue
├── Notifications/              # Laravel notification classes
├── Jobs/                       # Queued jobs (ProcessPayment, etc.)
└── Providers/
    └── AppServiceProvider.php  # Service bindings, rate limiters, macro registrations

database/
├── migrations/                 # MySQL schema migrations
├── factories/                  # Model factories for testing — required for all models
└── seeders/

tests/
├── Feature/Api/                # Full HTTP integration tests — one subdirectory per domain
│   ├── Auth/
│   ├── Product/
│   ├── Order/
│   └── ...
└── Unit/
    ├── Services/               # Unit tests for all Service classes
    └── Models/                 # Model relationship and scope tests
```

## Architecture Rules — STRICT

1. **Controllers are thin.** A controller method does exactly three things: validate input (via injected FormRequest), call a Service method, return a response via `ApiResponse`. No Eloquent queries. No business logic.

2. **Services own all business logic.** All DB queries, cache reads/writes, event dispatches, and complex logic live in Service classes. Services can call other Services. Never call `response()->json()` from a Service.

3. **Models are data containers.** Models define `$fillable`, `casts()`, relationships, and query scopes. No business logic in models.

4. **FormRequests for all mutating endpoints.** Every POST, PUT, PATCH endpoint must use a dedicated `FormRequest` subclass. Never use `Validator::make()` in controllers. The existing `AuthController` uses `Validator::make()` — this is legacy and will be migrated.

5. **API Resources wrap all model output.** Never return an Eloquent model or collection directly. Always wrap with an `ApiResource` or `SomeResource::collection()`. Resources must use an explicit field whitelist in `toArray()`.

6. **One Service per domain entity.** `ProductService`, `OrderService`, `VendorService`, etc. Inject via constructor DI only — no `app()` or `resolve()` inside methods.

## Standard Response Envelope

ALL API responses MUST use `App\Http\Responses\ApiResponse`. No exceptions.

### Success Response
```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { },
  "meta": {
    "timestamp": "2026-05-04T10:00:00Z"
  }
}
```

### Paginated List Response
`data` is the items array; `meta` additionally contains:
```json
{
  "meta": {
    "timestamp": "2026-05-04T10:00:00Z",
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 15,
      "total": 72,
      "from": 1,
      "to": 15
    }
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Human-readable error summary",
  "errors": {
    "field_name": ["Specific validation message"]
  },
  "meta": {
    "timestamp": "2026-05-04T10:00:00Z"
  }
}
```

`errors` is only present for 422 validation failures. Omit for 401, 403, 404, 500.

### ApiResponse Helper Usage
```php
// In controllers:
return ApiResponse::success('Product created.', new ProductResource($product), 201);
return ApiResponse::success('Products retrieved.', ProductResource::collection($products)->toArray($request), 200, $paginationMeta);
return ApiResponse::error('Not found.', 404);
return ApiResponse::validationError('Validation failed.', $validator->errors()->toArray());
```

File: `app/Http/Responses/ApiResponse.php` — **PRIORITY 1: must be created before any controller code works.**

## HTTP Status Code Conventions

| Scenario | Code |
|---|---|
| Successful GET / read | 200 |
| Successful POST / create | 201 |
| Successful DELETE (no body) | 204 |
| Validation failed | 422 |
| Unauthenticated | 401 |
| Forbidden (authenticated, no permission) | 403 |
| Resource not found | 404 |
| Server error | 500 |
| Rate limit exceeded | 429 |

Never return 200 for a failed operation. Never return 400 for a validation error (use 422). 401 vs 403 distinction is enforced.

## Auth System

### Sanctum Access Token
- Issued as Bearer token: `$user->createToken('access_token')->plainTextToken`
- Client sends: `Authorization: Bearer <token>`
- Protected routes: `middleware('auth:sanctum')`
- Lifetime: `config('sanctum.expiration')` (null = no auto-expiry)

### Refresh Token (Custom)
- Model: `App\Models\RefreshToken`
- Table: `refresh_tokens` — columns: `id, user_id, token (64-char unique), expires_at, revoked_at, timestamps`
- Lifecycle: 30-day expiry, rotation on every `/api/auth/refresh` call (old token revoked, new pair issued)
- `User` has `hasMany(RefreshToken::class)`

### OAuth (Socialite)
- Supported providers: Google, GitHub (add others via config)
- Table: `oauth_accounts` — `user_id, provider, provider_user_id, access_token, refresh_token, expires_at`
- Users may have `null` password (OAuth-only accounts)
- Flow: frontend obtains provider token → POST `/api/auth/oauth/{provider}` → backend validates via Socialite → upsert User + OAuthAccount → return Sanctum token pair

### Auth Routes
```
POST /api/auth/register
POST /api/auth/login
POST /api/auth/refresh
POST /api/auth/logout          [auth:sanctum]
GET  /api/auth/me              [auth:sanctum]
POST /api/auth/oauth/{provider}
```

## Database Strategy

### MySQL (primary)
- Relational, transactional: users, vendors, products (metadata), orders, order_items, payments, reviews
- Migrations in `database/migrations/`
- Always: `foreignId()->constrained()->cascadeOnDelete()` for FKs
- Always index: FK columns, `status` columns, any column in `WHERE` filters, `email` (unique), `token` (unique)

### MongoDB (catalogs + logs)
- Flexible schema: product descriptions, attributes, variant data, activity logs
- Connection key: `mongodb` in `config/database.php`
- Models extend `MongoDB\Laravel\Eloquent\Model`
- Collections: `product_details`, `activity_logs`, `search_cache`
- Install: `composer require mongodb/laravel-mongodb`

### Redis
- Cache: `CACHE_STORE=redis`, DB 0
- Queue: `QUEUE_CONNECTION=redis`, DB 0
- Session: `SESSION_DRIVER=redis`
- TTL conventions: product lists → 300s, user profile → 900s, category tree → 3600s

## Payment Gateway

Interface-based design in `app/Services/Payment/`:
```php
interface PaymentGatewayInterface {
    public function createPaymentIntent(array $data): array;
    public function capturePayment(string $paymentId): array;
    public function refundPayment(string $paymentId, int $amount): array;
    public function verifyWebhook(Request $request): bool;
}
```

Implementations: `StripePaymentService`, `MidtransPaymentService`.
Bound in `AppServiceProvider` based on `env('PAYMENT_GATEWAY', 'stripe')`.
Webhook routes are NOT under `auth:sanctum` — use signature verification (`verifyWebhook()`).

## Email Integration

- All emails in `app/Mail/`, extend `Illuminate\Mail\Mailable`, implement `ShouldQueue`
- Use Markdown Mailables: `php artisan make:mail OrderConfirmationMail --markdown`
- Triggered via Events/Listeners pattern (not directly from Service/Controller)
- Driver set via `MAIL_MAILER` env: `smtp`, `resend`, `ses`, `log` (dev)

Standard emails:
| Class | Trigger |
|---|---|
| `WelcomeMail` | On registration |
| `OrderConfirmationMail` | `OrderPlaced` event (to buyer) |
| `VendorNewOrderMail` | `OrderPlaced` event (to vendor) |
| `PaymentSuccessMail` | `PaymentCaptured` event |
| `PasswordResetMail` | Password reset request |

## Testing Standards

### Structure
- `tests/Feature/Api/{Domain}/` — one class per domain, one test file per controller
- `tests/Unit/Services/` — one class per Service
- `tests/Unit/Models/` — model relationship and scope tests

### Feature Test Requirements (ALL must apply)
1. Use `RefreshDatabase` trait
2. Assert exact JSON structure with `assertJsonStructure()`
3. Assert HTTP status code explicitly
4. Test the happy path AND at least one failure path (unauthenticated, validation fail, or not found)
5. Use factories only — never `User::create([...])` directly in tests
6. Always call `actingAs($user)` for protected routes

### Feature Test Template
```php
use RefreshDatabase;

public function test_authenticated_user_can_create_product(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/products', ['name' => 'Test Product', 'price' => 10000]);

    $response->assertStatus(201)
             ->assertJsonStructure([
                 'success', 'message',
                 'data' => ['id', 'name', 'price'],
                 'meta' => ['timestamp'],
             ])
             ->assertJson(['success' => true]);
}
```

### Unit Test Requirements
- Extend `PHPUnit\Framework\TestCase` (not Laravel's `TestCase`) for pure unit tests
- Mock all dependencies (no real DB, no HTTP)
- Test one method per test case

## Code Conventions

### Naming
| Thing | Convention | Example |
|---|---|---|
| DB tables | `snake_case` plural | `vendor_products` |
| Models | `PascalCase` singular | `VendorProduct` |
| Controllers | `PascalCase + Controller` | `VendorProductController` |
| Services | `PascalCase + Service` | `VendorProductService` |
| FormRequests | `{Action}{Domain}Request` | `StoreVendorProductRequest` |
| Resources | `{Domain}Resource` | `VendorProductResource` |
| Route names | `{resource}.{action}` | `vendor-products.index` |
| Events | `PascalCase` noun phrase | `OrderPlaced` |
| Listeners | `{Verb}{Noun}` | `SendOrderConfirmationEmail` |

### FormRequest Namespace
`App\Http\Requests\{Domain}\{Action}{Domain}Request`

### API Resource Namespace
`App\Http\Resources\{Domain}\{Model}Resource`

### Enums
```php
// app/Enums/OrderStatus.php
enum OrderStatus: string {
    case Pending  = 'pending';
    case Paid     = 'paid';
    case Shipped  = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
// In model casts:
protected function casts(): array { return ['status' => OrderStatus::class]; }
// In FormRequest rules:
'status' => ['required', Rule::enum(OrderStatus::class)],
```

### Monetary Values
Always store as integer cents. Expose in Resources as:
```php
'price_cents' => $this->price,
'price'       => number_format($this->price / 100, 2),
```

### API Resource Rules
- Explicit field whitelist in `toArray()` — never `parent::toArray($request)` or `$this->resource->toArray()`
- Null-safe dates: `$this->created_at?->toISOString()`
- Relations: `$this->whenLoaded('relation', fn() => new RelationResource($this->relation))`
- Enums: `$this->status->value`
- Never include `success`, `message`, or `meta` inside a Resource class

## Common Artisan Commands

```bash
# Code generation
php artisan make:model Product -mfsc        # Model + migration + factory + seeder + controller
php artisan make:request StoreProductRequest
php artisan make:resource ProductResource
php artisan make:mail OrderConfirmationMail --markdown
php artisan make:event OrderPlaced
php artisan make:listener SendOrderConfirmation --event=OrderPlaced
php artisan make:job ProcessPayment

# Database
php artisan migrate
php artisan migrate:status
php artisan migrate:fresh --seed            # DEV ONLY — destroys all data
php artisan db:show
php artisan tinker

# Tests
php artisan test
php artisan test --filter=ProductTest
php artisan test --coverage --min=80

# Cache
php artisan optimize:clear                  # Clears config + route + view + bootstrap cache

# Code quality
vendor/bin/pint                             # Format (Laravel Pint)
vendor/bin/pint --test                      # Dry run check
```

## Docker Environment

| Container | Image | Host Port | Purpose |
|---|---|---|---|
| `laravel-app` | Custom PHP 8.4-FPM (see `docker/php/Dockerfile`) | — | PHP application |
| `laravel-nginx` | `nginx:alpine` | `${APP_PORT:-8000}` | Web server |
| `laravel-mysql` | `mysql:8.0` | `${FORWARD_DB_PORT:-3306}` | MySQL |
| `laravel-redis` | `redis:alpine` | `${FORWARD_REDIS_PORT:-6379}` | Redis |

All containers share the `laravel` bridge network. MySQL host = `mysql`, Redis host = `redis` (as in `.env`).

```bash
# Start
docker compose up -d

# Run artisan inside container
docker compose exec app php artisan migrate
docker compose exec app php artisan test

# Logs
docker compose logs -f app
docker compose logs -f nginx

# Stop
docker compose down
```

## Implementation Roadmap (Missing Pieces)

Priority order — follow this sequence when building new features:

1. **`App\Http\Responses\ApiResponse`** — BLOCKER: nothing else works without this
2. Migrate `AuthController` to use FormRequests + `ApiResponse` (remove `Validator::make()`)
3. OAuth implementation (`AuthController@oauth` + `OAuthAccount` model)
4. MongoDB integration (`composer require mongodb/laravel-mongodb`)
5. Marketplace domains (build in this order): `Category`, `Vendor`, `Product`, `Order`+`OrderItem`, `Payment`, `Review`
6. `PaymentGatewayInterface` + Stripe implementation
7. Email: `App\Mail\` + Event/Listener wiring
8. Role/permission system (`spatie/laravel-permission`)
9. Rate limiting on auth endpoints (configure in `AppServiceProvider`)
10. Global exception handler in `bootstrap/app.php` (converts ModelNotFoundException, AuthorizationException to `ApiResponse::error()`)
