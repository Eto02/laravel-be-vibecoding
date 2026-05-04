# Laravel 13 Marketplace API — Project Context & SOP

## Project Purpose

Headless REST API backend for a **full-scale multi-vendor marketplace** (comparable to Tokopedia/Shopee). Serves mobile and SPA clients. No Blade/frontend in this repo. All responses are JSON. API prefix is `/api` (handled automatically by Laravel 13's `routes/api.php` binding).

---

## Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.3, Laravel 13 |
| Auth | Laravel Sanctum (Bearer) + custom refresh token rotation + Laravel Socialite (OAuth) |
| Primary DB | MySQL 8.0 — relational/transactional data |
| Cache / Queue / Session | Redis (phpredis) |
| Email | Laravel Mail — SMTP/Resend/SES (driver via `MAIL_MAILER` env) |
| File Storage | Cloudflare R2 — S3-compatible, zero egress fee. Upload via **presigned URL** (client-side direct upload). Minio for local dev. |
| Payment | Xendit — interface-based in `app/Services/Payment/` |
| Shipping | RajaOngkir / Biteship — interface-based in `app/Services/Shipping/` |
| Notifications | Laravel Notifications — Email, FCM Push, WhatsApp |
| Search | Laravel Scout + Meilisearch (P3) |
| Testing | PHPUnit 12 |
| Containerization | Docker Compose |
| Observability | Loki + Promtail + Grafana |

---

## Domain Modules

The platform is organized into **13 domain modules**. Each module owns its own controllers, requests, resources, and services. See `.claude/commands/make-module.md` for how to scaffold a module.

| # | Module | Priority | Description |
|---|---|---|---|
| 1 | **Auth** | 🔴 P0 | Register, Login, OAuth, Refresh Token, Email Verify, Password Reset |
| 2 | **User** | 🟠 P1 | Profile, Address Book, Phone Verify, Settings |
| 3 | **Merchant** | 🟠 P1 | Store Registration, Profile, KYC, Analytics, Followers |
| 4 | **Product** | 🟠 P1 | Category Tree, CRUD, Variants, Media, Inventory |
| 5 | **Cart** | 🟠 P1 | Add/Remove/Update Items, Multi-store Cart, Wishlist |
| 6 | **Order** | 🟠 P1 | Checkout, Status Flow, Cancellation, Dispute |
| 7 | **Payment** | 🟠 P1 | Multi-method, Wallet, Refund, Webhook |
| 8 | **Shipping** | 🟠 P1 | Ongkir Calc, AWB, Real-time Tracking |
| 9 | **Review** | 🟡 P2 | Rating, Comment, Merchant Reply, Moderation |
| 10 | **Notification** | 🟡 P2 | In-app, Email, Push (FCM), WhatsApp |
| 11 | **Voucher** | 🟡 P2 | Coupon, Flash Sale, Cashback, Loyalty Points |
| 12 | **Admin** | 🟡 P2 | User/Merchant/Product Moderation, Revenue Dashboard |
| 13 | **Search** | 🟢 P3 | Full-text, Autocomplete, Trending, Recommendation |

---

## Directory Structure (Modular)

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── Auth/                  # AuthController, OAuthController
│   │       ├── User/                  # UserController, AddressController
│   │       ├── Merchant/              # MerchantController, StoreController
│   │       ├── Product/               # ProductController, CategoryController, VariantController
│   │       ├── Cart/                  # CartController, WishlistController
│   │       ├── Order/                 # OrderController
│   │       ├── Payment/               # PaymentController, WebhookController
│   │       ├── Shipping/              # ShippingController
│   │       ├── Review/                # ReviewController
│   │       ├── Notification/          # NotificationController
│   │       ├── Voucher/               # VoucherController, FlashSaleController
│   │       └── Admin/                 # Admin-specific controllers
│   ├── Requests/
│   │   ├── Auth/                      # LoginRequest, RegisterRequest, ...
│   │   ├── User/                      # UpdateProfileRequest, StoreAddressRequest, ...
│   │   ├── Merchant/
│   │   ├── Product/
│   │   ├── Cart/
│   │   ├── Order/
│   │   ├── Payment/
│   │   ├── Shipping/
│   │   ├── Review/
│   │   └── Voucher/
│   ├── Resources/
│   │   ├── Auth/                      # TokenResource, ...
│   │   ├── User/                      # UserResource, AddressResource, ...
│   │   ├── Merchant/
│   │   ├── Product/                   # ProductResource, CategoryResource, ...
│   │   ├── Cart/
│   │   ├── Order/
│   │   ├── Payment/
│   │   ├── Shipping/
│   │   ├── Review/
│   │   └── Notification/
│   ├── Middleware/
│   │   ├── LogApiRequests.php         # Global — audit + Grafana logging
│   │   └── EnsureMerchantOwnership.php
│   └── Responses/
│       └── ApiResponse.php            # MANDATORY — all responses go through this
│
├── Models/                            # All models in root — domain grouped by naming
│   ├── User.php                       # [Auth/User]
│   ├── RefreshToken.php               # [Auth]
│   ├── OAuthAccount.php               # [Auth]
│   ├── Store.php                      # [Merchant]
│   ├── StoreDocument.php              # [Merchant]
│   ├── Category.php                   # [Product]
│   ├── Product.php                    # [Product]
│   ├── ProductVariant.php             # [Product]
│   ├── ProductMedia.php               # [Product]
│   ├── Address.php                    # [User]
│   ├── Cart.php                       # [Cart]
│   ├── CartItem.php                   # [Cart]
│   ├── Wishlist.php                   # [Cart]
│   ├── WishlistItem.php               # [Cart]
│   ├── Order.php                      # [Order]
│   ├── OrderItem.php                  # [Order]
│   ├── Transaction.php                # [Payment]
│   ├── Refund.php                     # [Payment]
│   ├── WalletBalance.php              # [Payment]
│   ├── WalletTransaction.php          # [Payment]
│   ├── Shipment.php                   # [Shipping]
│   ├── Review.php                     # [Review]
│   ├── Voucher.php                    # [Voucher]
│   ├── FlashSale.php                  # [Voucher]
│   ├── Notification.php               # [Notification]
│   └── ApiLog.php                     # [Monitoring]
│
├── Services/                          # Business logic — organized by domain
│   ├── Auth/
│   │   ├── AuthService.php
│   │   └── OAuthService.php
│   ├── User/
│   │   ├── UserService.php
│   │   └── AddressService.php
│   ├── Merchant/
│   │   └── MerchantService.php
│   ├── Product/
│   │   ├── ProductService.php
│   │   └── CategoryService.php
│   ├── Cart/
│   │   ├── CartService.php
│   │   └── WishlistService.php
│   ├── Order/
│   │   └── OrderService.php
│   ├── Payment/
│   │   ├── PaymentService.php
│   │   ├── WalletService.php
│   │   ├── PaymentGatewayInterface.php
│   │   └── Gateways/
│   │       └── XenditPaymentService.php
│   ├── Shipping/
│   │   ├── ShippingService.php
│   │   ├── ShippingProviderInterface.php
│   │   └── Providers/
│   │       └── RajaOngkirService.php
│   ├── Review/
│   │   └── ReviewService.php
│   ├── Voucher/
│   │   └── VoucherService.php
│   └── Shared/                        # Cross-cutting services used by ALL modules
│       ├── EmailService.php           # Wraps Laravel Mail — used by all modules
│       ├── OtpService.php             # Redis-backed OTP with rate/retry limit
│       ├── MediaService.php           # Cloudflare R2 Presigned Upload integration
│       └── IdempotencyService.php     # Mencegah request ganda via Redis
│
├── Enums/
│   ├── OrderStatus.php                # pending|paid|processing|shipped|delivered|completed|cancelled
│   ├── PaymentStatus.php              # pending|paid|failed|refunded|expired
│   ├── TransactionStatus.php          # pending|paid|expired
│   ├── ShipmentStatus.php             # pending|picked_up|in_transit|delivered|returned
│   ├── ProductStatus.php              # draft|active|inactive|banned
│   ├── MerchantStatus.php             # pending|active|suspended|banned
│   └── UserRole.php                   # buyer|merchant|admin
│
├── Events/
│   ├── Auth/
│   │   └── UserRegistered.php
│   ├── Order/
│   │   ├── OrderPlaced.php
│   │   └── OrderCancelled.php
│   └── Payment/
│       └── PaymentCaptured.php
│
├── DTOs/                              # Data Transfer Objects for complex Services
│   ├── Order/
│   │   └── CheckoutDTO.php
│   └── Product/
│       └── CreateProductDTO.php
│
├── Responses/                         # Standardized API response wrappers
│   └── ApiResponse.php                # MANDATORY Trait for Controllers
│
├── Listeners/
│   ├── Auth/
│   │   └── SendWelcomeEmail.php
│   ├── Order/
│   │   └── SendOrderConfirmationEmail.php
│   └── Payment/
│       └── UpdateTransactionStatus.php
│
├── Jobs/
│   ├── ProcessApiLog.php
│   ├── SendEmailJob.php               # Queued email dispatch
│   ├── SendPushNotificationJob.php    # Queued FCM dispatch
│   └── ProcessPaymentWebhook.php
│
├── Mail/
│   ├── Auth/
│   │   ├── WelcomeMail.php
│   │   ├── EmailVerificationMail.php
│   │   └── PasswordResetMail.php
│   ├── Order/
│   │   ├── OrderConfirmationMail.php
│   │   └── OrderShippedMail.php
│   └── Payment/
│       └── PaymentSuccessMail.php
│
└── Providers/
    └── AppServiceProvider.php         # DI bindings for all interfaces

routes/
├── api.php                            # Loads domain route files
└── api/
    ├── auth.php
    ├── user.php
    ├── merchant.php
    ├── product.php
    ├── cart.php
    ├── order.php
    ├── payment.php
    ├── shipping.php
    ├── review.php
    ├── notification.php
    ├── voucher.php
    └── admin.php

tests/
├── Feature/
│   └── Api/
│       ├── Auth/
│       │   ├── AuthTest.php
│       │   └── OAuthTest.php
│       ├── User/
│       ├── Merchant/
│       ├── Product/
│       ├── Cart/
│       ├── Order/
│       ├── Payment/
│       │   ├── PaymentTest.php
│       │   └── WebhookTest.php
│       ├── Shipping/
│       ├── Review/
│       ├── Voucher/
│       └── Monitoring/
│           └── LoggingTest.php
└── Unit/
    ├── Services/
    │   ├── Auth/
    │   ├── Order/
    │   ├── Payment/
    │   └── Shared/
    └── Models/

---

## API Response Standard

Semua Controller WAJIB menggunakan `ApiResponse` trait. Format JSON harus konsisten:

### Success Response (200/201)
```json
{
    "success": true,
    "message": "Product created successfully",
    "data": { ... }
}
```

### Error Response (4xx/5xx)
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

---

## Data Transfer Pattern (DTO)

Untuk method Service yang menerima > 2 parameter, gunakan `readonly class` DTO.

```php
// app/DTOs/Order/CheckoutDTO.php
readonly class CheckoutDTO {
    public function __construct(
        public int $addressId,
        public array $items,
        public ?string $voucherCode,
    ) {}
}

// In Controller:
$this->orderService->process(CheckoutDTO::fromRequest($request));

// In Service:
public function process(CheckoutDTO $data): Order { ... }
```

---

---

## Architecture Rules — STRICT

1. **Controllers are thin.** A controller method does exactly three things: validate input (via injected `FormRequest`), call a Service method, return a response via `ApiResponse`. No Eloquent queries. No business logic. No `Validator::make()`.

2. **Services own all business logic.** All DB queries, cache reads/writes, event dispatches, and complex logic live in Service classes. Services can call other Services. Never call `response()->json()` from a Service.

3. **Models are data containers.** Models define `$fillable`, `casts()`, relationships, and query scopes. No business logic in models.

4. **Domain folder separation is mandatory.** Every new file MUST be placed in the correct domain subfolder:
   - Controllers → `app/Http/Controllers/Api/{Domain}/`
   - Requests → `app/Http/Requests/{Domain}/`
   - Resources → `app/Http/Resources/{Domain}/`
   - Services → `app/Services/{Domain}/`
   - Tests → `tests/Feature/Api/{Domain}/`
   - Routes → `routes/api/{domain}.php`

5. **Shared/cross-cutting services live in `app/Services/Shared/`.** If a service is used by more than one domain (e.g., Email, SMS, OTP, File Upload, Push Notification), it MUST go in `Shared/`. Domain services inject Shared services via constructor.

6. **FormRequests for all mutating endpoints.** Every `POST`, `PUT`, `PATCH` endpoint must use a dedicated `FormRequest` subclass.

7. **API Resources wrap all model output.** Never return an Eloquent model or collection directly. Always use a Resource with an explicit `toArray()` whitelist.

8. **Routes are split per domain.** `routes/api.php` only loads domain route files. No route definitions directly in `routes/api.php` except the `require` statements.

9. **One Service per domain entity.** `ProductService`, `OrderService`, etc. Inject via constructor DI only.

10. **Idempotency for Mutating Actions.** Semua API yang mengubah state finansial atau inventori (Order, Payment, Payout) WAJIB mendukung header `X-Idempotency-Key`. Gunakan `IdempotencyService` untuk memvalidasi key di Redis sebelum memproses logic.

11. **Event-Driven Notifications.** DILARANG menggunakan `NotificationService`. Gunakan Laravel Events (misal `OrderPlaced`). Listener yang akan menangani pengiriman Email, Push, atau WA secara asinkron (`ShouldQueue`).

12. **Data Transfer Objects (DTO).** Untuk service method yang menerima > 2 parameter, WAJIB menggunakan DTO (class readonly). DILARANG mengirim `$request->all()` langsung ke Service.

13. **Service Return Type.** Service harus mengembalikan Model, DTO, atau boolean. Gunakan **Exceptions** untuk alur error (misal: `InsufficientStockException`), jangan return `['error' => '...']`.

14. **Git Workflow (PR-first).** DILARANG push langsung ke `main`. Selalu buat branch `feat/nama-fitur` atau `fix/nama-bug`. Setelah selesai, push ke remote dan buat Pull Request untuk review.

15. **API Documentation.** Setiap penambahan endpoint WAJIB diiringi dengan update koleksi Postman. Simpan file `postman_collection.json` di root repository atau gunakan auto-generator (Scribe).


---

## Shared Services SOP

These services are **pre-built and ready to inject** into any domain service. Never re-implement them per-module.

### `App\Services\Shared\EmailService`
```php
// Usage in any Service:
public function __construct(private readonly EmailService $email) {}

// Send a Mailable:
$this->email->send($user, new OrderConfirmationMail($order));

// Send a plain email without a Mailable:
$this->email->sendRaw($user->email, 'Subject', 'Body text');
```

### `App\Services\Shared\MediaService` (Cloudflare R2 — Presigned Upload)
**Upload flow:** Backend hanya generate presigned URL, file dikirim langsung dari client ke R2.
```php
// Step 1 — generate presigned URL (dipanggil dari controller)
$result = $this->media->generatePresignedUrl('products', 'photo.jpg', 'image/jpeg');
// returns: ['upload_url' => '...', 'key' => 'products/uuid.jpg', 'public_url' => '...']

// Step 2 — client melakukan PUT ke upload_url secara langsung (frontend/mobile)

// Step 3 — konfirmasi & simpan key ke DB
$exists = $this->media->confirmUpload('products/uuid.jpg');

// Get URL
$this->media->publicUrl('products/uuid.jpg');          // untuk file public
$this->media->temporaryUrl('kyc-documents/ktp.jpg');   // untuk file private

// Delete
$this->media->delete('products/uuid.jpg');
```

### `App\Services\Shared\OtpService`
// Generate & store OTP (Redis, TTL 5 min):
$otp = $this->otp->generate($identifier);

// Verify OTP (dengan rate limit & retry limit):
$valid = $this->otp->verify($identifier, $inputOtp);

### `App\Services\Shared\IdempotencyService`
// Pastikan request tidak diproses dua kali (Order/Payment):
$this->idempotency->check($request->header('X-Idempotency-Key'), function() use ($data) {
    return $this->order->create($data);
});

### `App\Services\Shared\CacheService`
// Native Laravel Cache contract (simplified):
$this->cache->remember('key', 3600, fn() => 'value');

---

## Order State Machine

Status transitions harus mengikuti alur yang valid. Dilarang melompat status tanpa trigger yang sesuai.

| From | To | Trigger |
|---|---|---|
| `pending` | `paid` | Payment Webhook (Success) |
| `pending` | `cancelled` | Expiry Job / User Cancel |
| `paid` | `processing` | Merchant Accept |
| `paid` | `cancelled` | Merchant Reject (Auto-refund) |
| `processing` | `shipped` | Merchant Input AWB |
| `shipped` | `delivered` | Courier API / User Confirm |
| `delivered` | `completed` | Auto-complete (3 days) / User Confirm |
| `any` | `disputed` | User Complaint |

---

## Standard Response Envelope

ALL API responses MUST use `App\Http\Responses\ApiResponse`. No exceptions.

```json
// Success
{ "success": true, "message": "...", "data": {}, "meta": { "timestamp": "..." } }

// Paginated
{ "success": true, "message": "...", "data": [], "meta": { "timestamp": "...", "pagination": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 72 } } }

// Error
{ "success": false, "message": "...", "meta": { "timestamp": "..." } }

// Validation Error (422 only)
{ "success": false, "message": "...", "errors": { "field": ["message"] }, "meta": { "timestamp": "..." } }
```

---

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
| Rate limit exceeded | 429 |
| Server error | 500 |

---

## Auth System

### Sanctum Access Token
- Issued as Bearer token: `$user->createToken('access_token')->plainTextToken`
- Client sends: `Authorization: Bearer <token>`
- Protected routes: `middleware('auth:sanctum')`

### Refresh Token (Custom)
- Model: `App\Models\RefreshToken`
- Table: `refresh_tokens` — columns: `id, user_id, token, expires_at, revoked_at, timestamps`
- Lifecycle: 30-day expiry, rotation on every `/api/auth/refresh` call

### OAuth (Socialite)
- Supported providers: Google, GitHub
- Table: `oauth_accounts` — `user_id, provider, provider_user_id, access_token, refresh_token, expires_at`
- Flow: frontend obtains provider token → POST `/api/auth/oauth/{provider}` → backend validates → return Sanctum token pair

### Auth Routes
```
POST /api/auth/register
POST /api/auth/login
POST /api/auth/refresh
POST /api/auth/logout          [auth:sanctum]
GET  /api/auth/me              [auth:sanctum]
POST /api/auth/oauth/{provider}
POST /api/auth/email/verify
POST /api/auth/email/resend
POST /api/auth/forgot-password
POST /api/auth/reset-password
PUT  /api/auth/change-password [auth:sanctum]
```

---

## Database Strategy

### MySQL (primary)
- All relational, transactional data
- Migrations in `database/migrations/`
- Always: `foreignId()->constrained()->cascadeOnDelete()` for FKs
- Always index: FK columns, `status` columns, columns used in `WHERE` filters
- Monetary values: always store as **integer cents** (e.g. Rp 50.000 → `5000000`)

### Redis
- Cache: `CACHE_STORE=redis`, DB 0
- Queue: `QUEUE_CONNECTION=redis`, DB 0
- Session: `SESSION_DRIVER=redis`
- TTL conventions:
  - Product lists → 300s
  - Category tree → 3600s
  - User profile → 900s
  - OTP → 300s
  - Cart session → 86400s

---

## Payment Gateway

Interface pattern in `app/Services/Payment/`:
```php
interface PaymentGatewayInterface {
    public function createInvoice(array $data): array;
    public function capturePayment(string $paymentId): array;
    public function refundPayment(string $paymentId, int $amount): array;
    public function verifyWebhook(Request $request): bool;
}
```
Bound in `AppServiceProvider` based on `env('PAYMENT_GATEWAY', 'xendit')`.
Webhook routes are NOT under `auth:sanctum` — use `verifyWebhook()` signature verification.

---

## Shipping Provider

Interface pattern in `app/Services/Shipping/`:
```php
interface ShippingProviderInterface {
    public function calculateCost(array $params): array;  // origin, destination, weight, courier
    public function getAvailableCouriers(): array;
    public function trackShipment(string $awb, string $courier): array;
}
```
Bound in `AppServiceProvider` based on `env('SHIPPING_PROVIDER', 'rajaongkir')`.

---

## Email Integration

- All emails in `app/Mail/{Domain}/`, extend `Illuminate\Mail\Mailable`, implement `ShouldQueue`
- **Never call `Mail::send()` directly from a Service or Controller.** Always use `EmailService` or fire an Event that triggers a Listener.
- Driver set via `MAIL_MAILER` env: `smtp`, `resend`, `ses`, `log` (dev)

Standard emails:
| Class | Trigger |
|---|---|
| `Auth\WelcomeMail` | `UserRegistered` event |
| `Auth\EmailVerificationMail` | On register / resend verify |
| `Auth\PasswordResetMail` | Password reset request |
| `Order\OrderConfirmationMail` | `OrderPlaced` event (to buyer) |
| `Order\OrderShippedMail` | Order status → shipped |
| `Payment\PaymentSuccessMail` | `PaymentCaptured` event |

---

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| DB tables | `snake_case` plural | `order_items`, `product_variants` |
| Models | `PascalCase` singular | `OrderItem`, `ProductVariant` |
| Controllers | `{Domain}/{Name}Controller` | `Order/OrderController` |
| Services | `{Domain}/{Name}Service` | `Order/OrderService` |
| Shared Services | `Shared/{Name}Service` | `Shared/EmailService` |
| FormRequests | `{Domain}/{Action}{Domain}Request` | `Order/StoreOrderRequest` |
| Resources | `{Domain}/{Name}Resource` | `Order/OrderResource` |
| Route names | `{domain}.{resource}.{action}` | `order.items.index` |
| Enums | `{Domain}Status` | `OrderStatus`, `ProductStatus` |
| Events | `{Domain}/{PascalNoun}` | `Order/OrderPlaced` |
| Mails | `{Domain}/{Name}Mail` | `Order/OrderConfirmationMail` |

### Namespace Patterns
```
App\Http\Controllers\Api\{Domain}\{Name}Controller
App\Http\Requests\{Domain}\{Action}{Name}Request
App\Http\Resources\{Domain}\{Name}Resource
App\Services\{Domain}\{Name}Service
App\Services\Shared\{Name}Service
App\Events\{Domain}\{EventName}
App\Mail\{Domain}\{Name}Mail
Tests\Feature\Api\{Domain}\{Name}Test
```

---

## API Resource Rules

- Explicit field whitelist in `toArray()` — never `parent::toArray($request)`
- Null-safe dates: `$this->created_at?->toISOString()`
- Relations: `$this->whenLoaded('relation', fn() => new RelationResource($this->relation))`
- Enums: `$this->status->value`
- Monetary: always expose both `price_cents` and `price` (formatted)
- Never include `success`, `message`, or `meta` inside a Resource

---

## Enums

```php
// app/Enums/OrderStatus.php
enum OrderStatus: string {
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Processing = 'processing';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
// In model casts:
protected function casts(): array { return ['status' => OrderStatus::class]; }
// In FormRequest rules:
'status' => ['required', Rule::enum(OrderStatus::class)],
```

---

## Testing Standards

### Structure
- `tests/Feature/Api/{Domain}/` — one test class per controller
- `tests/Unit/Services/{Domain}/` — one class per Service
- `tests/Unit/Models/` — model relationship and scope tests

### Feature Test Requirements (ALL must apply)
1. Use `RefreshDatabase` trait
2. Assert exact JSON structure with `assertJsonStructure()`
3. Assert HTTP status code explicitly
4. Test happy path AND at least one failure path (unauthenticated, validation fail, not found)
5. Use factories only — never `User::create([...])` directly
6. Always call `actingAs($user)` for protected routes

---

## Route File Pattern

`routes/api.php` must follow this pattern exactly:
```php
<?php
// routes/api.php — Domain Route Loader

foreach ([
    'auth', 'user', 'merchant', 'product', 'cart',
    'order', 'payment', 'shipping', 'review',
    'notification', 'voucher', 'admin',
] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
```

Each domain file (`routes/api/order.php`) example:
```php
<?php
use App\Http\Controllers\Api\Order\OrderController;

Route::middleware('auth:sanctum')->prefix('orders')->name('order.orders.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::post('/', [OrderController::class, 'store'])->name('store');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');
    Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
});
```

---

## Common Artisan Commands

```bash
# Code generation (always run inside Docker container)
docker compose exec app php artisan make:model Product -mfc
docker compose exec app php artisan make:request Product/StoreProductRequest
docker compose exec app php artisan make:resource Product/ProductResource
docker compose exec app php artisan make:mail Order/OrderConfirmationMail --markdown
docker compose exec app php artisan make:event Order/OrderPlaced
docker compose exec app php artisan make:listener Order/SendOrderConfirmation --event=Order/OrderPlaced
docker compose exec app php artisan make:job ProcessPayment

# Database
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed   # DEV ONLY
docker compose exec app php artisan db:show

# Tests
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=OrderTest
docker compose exec app php artisan test --coverage --min=80

# Cache
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:clear
```

---

## Docker Environment

| Container | Purpose | Host Port |
|---|---|---|
| `laravel-app` | PHP 8.3-FPM Application | — |
| `laravel-worker` | Queue Worker (async jobs) | — |
| `laravel-nginx` | Web Server | `${APP_PORT:-8000}` |
| `laravel-mysql` | MySQL 8.0 | `${FORWARD_DB_PORT:-3306}` |
| `laravel-redis` | Redis | `${FORWARD_REDIS_PORT:-6379}` |
| `laravel-loki` | Log Storage (Loki) | `3100` |
| `laravel-promtail` | Log Scraper | — |
| `laravel-grafana` | Monitoring Dashboard | `3000` |

All containers share the `laravel` bridge network.

---

## Implementation Roadmap

Follow this priority when a new feature is requested. Never skip an earlier-priority item to do a later one.

**P0 — Must have (before any other module works)**
1. `App\Http\Responses\ApiResponse` — BLOCKER
2. Global exception handler in `bootstrap/app.php`
3. Auth module completion (email verify, password reset)

**P1 — Core marketplace (build in this order)**
4. User Profile + Address Book
5. Merchant + Store Registration
6. Category Tree
7. Product CRUD + Variants + Media
8. Cart + Wishlist
9. Order Management (checkout → status flow)
10. Payment (multi-method + wallet + refund)
11. Shipping (ongkir calc + tracking)

**P2 — Growth features**
12. Review & Rating
13. Notification System (Email + Push + WA)
14. Voucher + Flash Sale
15. Admin Panel API
16. Role/Permission (`spatie/laravel-permission`)

**P3 — Scale**
17. Search (Laravel Scout + Meilisearch)
18. Recommendation Engine
19. Analytics & Reporting

---

## Git & Contribution Workflow

Selalu ikuti alur ini untuk menjaga integritas `main` branch:

1.  **Sync:** `git checkout main && git pull origin main`
2.  **Branch:** `git checkout -b feat/feature-name` (Gunakan prefix `feat/`, `fix/`, `refactor/`, atau `chore/`)
3.  **Code:** Lakukan perubahan dan commit secara atomik.
4.  **Test:** Pastikan `php artisan test` lulus semua.
5.  **Push:** `git push origin feat/feature-name`
6.  **PR:** Buka Pull Request di GitHub. Berikan deskripsi perubahan dan lampirkan screenshot/recording jika ada perubahan UI/logic signifikan.
7.  **Merge:** Dilakukan setelah review atau jika CI/CD lulus.

---

## API Documentation (Postman)

Agar API mudah dicoba oleh tim Frontend atau QA:

1.  **Collection File:** Gunakan file `docs/marketplace_api.postman_collection.json` untuk menyimpan semua request.
2.  **Environment:** Sediakan `docs/marketplace_dev.postman_environment.json` yang berisi variable `base_url`, `token`, dll.
3.  **Authentication:** Set authorization di level folder/collection menggunakan `Bearer Token` dari variable `{{token}}`.
4.  **Examples:** Simpan contoh response (Success & Error) di setiap request Postman agar frontend tahu struktur data tanpa harus menjalankan API.
5.  **Automated Doc (Optional):** Kita bisa menggunakan `knuckleswtf/scribe` untuk generate dokumentasi HTML dan Postman collection secara otomatis dari DocBlock di Controller.
