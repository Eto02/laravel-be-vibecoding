# Laravel 13 Marketplace API — Context & SOP

Headless REST API for a multi-vendor marketplace (Tokopedia/Shopee scale). JSON-only, no Blade. API prefix: `/api`.

---

## Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.3, Laravel 13 |
| Auth | Sanctum (Bearer) + custom refresh token rotation + Socialite (OAuth) |
| Primary DB | MySQL 8.0 |
| Cache / Queue / Session | Redis (phpredis) |
| Email | Laravel Mail — driver via `MAIL_MAILER` env (smtp/resend/ses/log) |
| File Storage | Cloudflare R2 — presigned URL upload (client-side direct). Minio for local dev. |
| Payment | Xendit — interface-based in `app/Services/Payment/` |
| Shipping | RajaOngkir / Biteship — interface-based in `app/Services/Shipping/` |
| Notifications | Laravel Notifications — Email, FCM Push, WhatsApp |
| Search | Laravel Scout + Meilisearch (P3) |
| Testing | PHPUnit 12 |
| Container | Docker Compose |
| Observability | Loki + Promtail + Grafana |

---

## Domain Modules

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

## Directory Structure

```
app/Http/Controllers/Api/{Domain}/    app/Http/Requests/{Domain}/
app/Http/Resources/{Domain}/          app/Http/Middleware/
app/Http/Responses/ApiResponse.php    ← MANDATORY, all responses go through this
app/Models/                           ← flat, all models here (User, Order, Product, …)
app/Services/{Domain}/                app/Services/Shared/
app/Enums/   app/Events/{Domain}/     app/DTOs/{Domain}/
app/Listeners/{Domain}/  app/Jobs/    app/Mail/{Domain}/
app/Providers/AppServiceProvider.php  ← DI bindings for all interfaces

routes/api.php  →  routes/api/{domain}.php  (12 domain files)
tests/Feature/Api/{Domain}/           tests/Unit/Services/{Domain}/
api-collections/{nn}-{domain}.collection.json
```

**Models:** `User, RefreshToken, OAuthAccount, Store, StoreDocument, Category, Product, ProductVariant, ProductMedia, Address, Cart, CartItem, Wishlist, WishlistItem, Order, OrderItem, Transaction, Refund, WalletBalance, WalletTransaction, Shipment, Review, Voucher, FlashSale, Notification, ApiLog`

**Enums:** `OrderStatus, PaymentStatus, TransactionStatus, ShipmentStatus, ProductStatus, MerchantStatus, UserRole`

---

## Architecture Rules — STRICT

1. **Controllers are thin.** FormRequest → Service → ApiResponse. No Eloquent, no business logic, no `Validator::make()`. `$this->authorize()` is allowed (delegates to Policy).
2. **Services own all business logic.** All DB queries, cache, event dispatches. Never `response()->json()` from a Service.
3. **Models are data containers.** `$fillable`, `casts()`, relationships, scopes only. No logic.
4. **Domain folder separation.** Every file in correct domain subfolder: Controllers / Requests / Resources / Services / Tests / Routes.
5. **Shared services in `app/Services/Shared/`.** Used by >1 domain → must be in Shared. Inject via constructor DI.
6. **FormRequests for all mutating endpoints with input.** POST/PUT/PATCH with body → dedicated FormRequest. Bodyless actions (cancel, delete) may use plain `Request`.
7. **API Resources wrap all output.** Explicit `toArray()` whitelist. Never return Eloquent model directly.
8. **Routes split per domain.** `routes/api.php` only loads domain files — no route definitions there.
9. **One Service per domain entity.** Constructor DI only.
10. **Idempotency for financial mutations.** Order/Payment/Payout MUST support `X-Idempotency-Key` via `IdempotencyService`.
11. **Event-driven notifications.** No `NotificationService`. Use Laravel Events + `ShouldQueue` Listeners.
12. **DTOs for >2 params.** `readonly class` DTO. Never pass `$request->all()` to Service.
13. **Service return types.** Return Model, DTO, or bool. Use Exceptions for errors — never `['error' => '...']`.
14. **Sprint Execution Workflow.** All code changes must follow the workflow below. Push/PR/merge only via user slash commands.
15. **API Documentation.** Every new endpoint → `api-collections/{nn}-{domain}.collection.json` + `python3 api-collections/merge.py` before stop. Part of Definition of Done.

---

## Standard Response Envelope

Use `App\Http\Responses\ApiResponse` static methods. **No exceptions.**

```json
{ "success": true,  "message": "...", "data": {},  "meta": { "timestamp": "..." } }
{ "success": true,  "message": "...", "data": [],  "meta": { "timestamp": "...", "pagination": { "current_page":1,"last_page":5,"per_page":15,"total":72 } } }
{ "success": false, "message": "...",               "meta": { "timestamp": "..." } }
{ "success": false, "message": "...", "errors": { "field": ["msg"] }, "meta": { "timestamp": "..." } }
```

## HTTP Status Codes

| Scenario | Code | Scenario | Code |
|---|---|---|---|
| GET / read | 200 | Validation failed | 422 |
| POST / create | 201 | Unauthenticated | 401 |
| DELETE (no body) | 204 | Forbidden | 403 |
| — | — | Not found | 404 |
| — | — | Rate limit | 429 |
| — | — | Server error | 500 |

---

## Auth System

- **Sanctum** — `$user->createToken('access_token')->plainTextToken`, protected via `auth:sanctum`
- **Refresh Token** — `refresh_tokens(id, user_id, token, expires_at, revoked_at)`. 30-day expiry, rotated on every `/api/auth/refresh`
- **OAuth** — `oauth_accounts(user_id, provider, provider_user_id, access_token, refresh_token, expires_at)`. Flow: frontend gets provider token → `POST /api/auth/oauth/{provider}` → Sanctum token pair

**Routes:** `POST register|login|refresh|logout|oauth/{provider}|email/verify|email/resend|forgot-password|reset-password` · `GET me` · `PUT change-password` (all prefixed `/api/auth/`)

---

## Database

**MySQL:** FKs always `foreignId()->constrained()->cascadeOnDelete()`. Index FK/status/WHERE columns. Monetary = **integer cents** (Rp 50.000 → `5000000`).

**Redis TTL:** Product lists 300s · Category tree 3600s · User profile 900s · OTP 300s · Cart 86400s

---

## Shared Services (pre-built — do not re-implement per module)

```php
// EmailService
$this->email->send($user, new OrderConfirmationMail($order));
$this->email->sendRaw($user->email, 'Subject', 'Body');

// MediaService — client uploads directly to R2 via presigned URL
$result = $this->media->generatePresignedUrl('products', 'photo.jpg', 'image/jpeg');
// → ['upload_url'=>'...', 'key'=>'products/uuid.jpg', 'public_url'=>'...']
$this->media->confirmUpload('products/uuid.jpg');
$this->media->publicUrl('key') / temporaryUrl('private/key') / delete('key');

// OtpService
$otp   = $this->otp->generate($identifier);           // Redis, TTL 5min
$valid = $this->otp->verify($identifier, $inputOtp);  // rate-limited

// IdempotencyService
$this->idempotency->check($request->header('X-Idempotency-Key'), fn() => $this->order->create($data));

// CacheService
$this->cache->remember('key', 3600, fn() => 'value');
```

---

## Order State Machine

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

## Payment Gateway Interface

```php
interface PaymentGatewayInterface {
    public function createCharge(array $data): array;         // → gateway_ref, redirect_url, payment_details, expires_at
    public function cancelCharge(string $ref, string $method): void;
    public function getPaymentStatus(string $ref): array;
    public function refundPayment(string $ref, int $amount): array;
    public function verifyWebhook(Request $request): bool;
    public function parseWebhookPayload(Request $request): array; // → event, external_id, status, amount
}
```
Named bindings: `payment.xendit`, `payment.midtrans`. Expiry via `PAYMENT_EXPIRY_MINUTES` (default 15). Webhook routes NOT under `auth:sanctum`.

## Shipping Provider Interface

```php
interface ShippingProviderInterface {
    public function calculateCost(array $params): array;  // origin, destination, weight, courier
    public function getAvailableCouriers(): array;
    public function trackShipment(string $awb, string $courier): array;
}
```
Bound via `env('SHIPPING_PROVIDER', 'rajaongkir')`.

---

## Email

All Mailables in `app/Mail/{Domain}/`, implement `ShouldQueue`. Always use `EmailService` or fire an Event — never `Mail::send()` directly from Service/Controller.

| Mailable | Trigger |
|---|---|
| `Auth\WelcomeMail` | `UserRegistered` |
| `Auth\EmailVerificationMail` | Register / resend |
| `Auth\PasswordResetMail` | Password reset request |
| `Order\OrderConfirmationMail` | `OrderPlaced` |
| `Order\OrderShippedMail` | Order → shipped |
| `Payment\PaymentSuccessMail` | `PaymentCaptured` |

---

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| DB tables | `snake_case` plural | `order_items` |
| Models | `PascalCase` singular | `OrderItem` |
| Controllers | `Api/{Domain}/{Name}Controller` | `Api/Order/OrderController` |
| Services | `{Domain}/{Name}Service` | `Order/OrderService` |
| Shared Services | `Shared/{Name}Service` | `Shared/EmailService` |
| FormRequests | `{Domain}/{Action}{Name}Request` | `Order/StoreOrderRequest` |
| Resources | `{Domain}/{Name}Resource` | `Order/OrderResource` |
| Route names | `{domain}.{resource}.{action}` | `order.orders.index` |
| Enums | `{Name}Status` | `OrderStatus` |
| Events | `{Domain}/{EventName}` | `Order/OrderPlaced` |
| Mails | `{Domain}/{Name}Mail` | `Order/OrderConfirmationMail` |

**Namespace pattern:** `App\Http\Controllers\Api\{Domain}\{Name}Controller` — same logic for Requests, Resources, Services, Events, Mail, Tests.

---

## API Resource Rules

- Explicit `toArray()` whitelist — never `parent::toArray($request)`
- Dates: `$this->created_at?->toISOString()`
- Relations: `$this->whenLoaded('relation', fn() => new RelationResource($this->relation))`
- Enums: `$this->status->value` · Monetary: expose both `price_cents` and `price` (formatted)
- Never include `success`, `message`, or `meta` inside a Resource

---

## DTO Pattern (required for >2 service params)

```php
readonly class CheckoutDTO {
    public function __construct(
        public int $addressId,
        public array $items,
        public ?string $voucherCode,
    ) {}
}
// Controller: $this->orderService->process(CheckoutDTO::fromRequest($request));
// Service:    public function process(CheckoutDTO $data): Order { ... }
```

## Enums

```php
enum OrderStatus: string {
    case Pending = 'pending'; case Paid = 'paid'; case Processing = 'processing';
    case Shipped = 'shipped'; case Delivered = 'delivered';
    case Completed = 'completed'; case Cancelled = 'cancelled';
}
// Model cast:       'status' => OrderStatus::class
// FormRequest rule: Rule::enum(OrderStatus::class)
```

---

## Testing Standards

- Feature: `tests/Feature/Api/{Domain}/` — one class per controller
- Unit: `tests/Unit/Services/{Domain}/` — one class per Service

**Every feature test must:** use `RefreshDatabase` · assert status code + `assertJsonStructure()` · test happy path + one failure (401/422/404) · use factories only · call `actingAs($user)` for protected routes.

---

## Route File Pattern

```php
// routes/api.php
foreach (['auth','user','merchant','product','cart','order','payment','shipping','review','notification','voucher','admin'] as $domain) {
    require __DIR__."/api/{$domain}.php";
}

// routes/api/order.php
Route::middleware('auth:sanctum')->prefix('orders')->name('order.orders.')->group(function () {
    Route::get('/',             [OrderController::class, 'index'])->name('index');
    Route::post('/',            [OrderController::class, 'store'])->name('store');
    Route::get('/{order}',      [OrderController::class, 'show'])->name('show');
    Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
});
```

---

## Common Commands

```bash
# Generate (always inside Docker)
docker compose exec app php artisan make:model Product -mfc
docker compose exec app php artisan make:request Product/StoreProductRequest
docker compose exec app php artisan make:resource Product/ProductResource
docker compose exec app php artisan make:mail Order/OrderConfirmationMail --markdown
docker compose exec app php artisan make:event Order/OrderPlaced

# DB / Test / Cache
docker compose exec app php artisan migrate
docker compose exec app php artisan test [--filter=OrderTest] [--coverage --min=80]
docker compose exec app php artisan optimize:clear
```

---

## Docker Containers

| Container | Purpose | Port |
|---|---|---|
| `laravel-app` | PHP 8.3-FPM | — |
| `laravel-worker` | Queue Worker | — |
| `laravel-nginx` | Web Server | `${APP_PORT:-8000}` |
| `laravel-mysql` | MySQL 8.0 | `${FORWARD_DB_PORT:-3306}` |
| `laravel-redis` | Redis | `${FORWARD_REDIS_PORT:-6379}` |
| `laravel-loki` | Log Storage | `3100` |
| `laravel-promtail` | Log Scraper | — |
| `laravel-grafana` | Monitoring | `3000` |

---

## Implementation Roadmap

**P0:** `ApiResponse` class · Global exception handler · Auth completion  
**P1 (in order):** User · Merchant · Category · Product · Cart · Order · Payment · Shipping  
**P2:** Review · Notification · Voucher · Admin · `spatie/laravel-permission`  
**P3:** Search (Scout + Meilisearch) · Recommendation · Analytics

---

## Sprint Execution Workflow

All code changes MUST follow this workflow. Push/PR/merge only via user slash commands — never autonomously.

### Slash Commands

| Command | Action |
|---|---|
| `/plan-review {module}` | Read planning doc, discuss — **no code changes** |
| `/execute {module}` | Issue → branch → implement → commit → stop with self-review |
| `/push` | Push current branch to remote |
| `/pr` | Create PR to main |
| `/merge-ok` | User-approved → squash merge + delete branch |
| `/devseed` | `migrate:fresh --seed` (dev only) |
| `/make-feature {Domain}/{Feature}` | Scaffold 11 files: migration, model, factory, requests, resource, service, controller, route, test |
| `/make-shared-service {Name}` | Scaffold Shared Service + binding + unit test |
| `/api-audit` | Standards compliance audit |
| `/db-status` | DB / Redis health check |
| `/test-suite [filter]` | Run test suite |

`laravel-reviewer` subagent — invoke via `Agent(subagent_type=laravel-reviewer)` for pre-PR code review.

### Sprint Cycle

```
1. /plan-review {N}-{module}     → discuss, no code
2. /execute {N}-{module}
   → gh issue create
   → git checkout main && git pull && git checkout -b feat/sprint-N-{module}
   → implement all, atomic commits (NO push)
   → update api-collections/{nn}-domain.collection.json + run merge.py
   → update planning/NN-module.md → ✅ Selesai
   → update DevSeeder (if new entities)
   → php artisan test (must pass)
   → send self-review report → STOP
3. User reviews in IDE
4. /push → /pr → (optional) /ultrareview {PR} in UI → /merge-ok
```

### Mandatory Rules

1. **Plan-first.** No `/execute` without `/plan-review` (unless user explicitly skips).
2. **Issue per module.** `/execute` always starts with `gh issue create`.
3. **Branch from main.** Name: `feat/sprint-N-{module}` / `fix/{desc}` / `chore/{desc}`.
4. **Local commits OK, no push.** Push only after `/push`.
5. **Definition of Done** (all required before stop):
   - `php artisan test` passes
   - `api-collections/{nn}-domain.collection.json` updated + `python3 api-collections/merge.py` run
   - `planning/NN-module.md` checkboxes updated → `✅ Selesai`
   - DevSeeder updated if new entities
   - Self-review report sent
6. **No push/PR/merge without explicit slash command.**
7. **Bugs found during sprint:** fix inline on current branch. Standalone bugs → own `fix/...` branch.
8. **Conflicts:** assistant rebases, reports to user if resolution needed.

### Meta-Change Workflow (CLAUDE.md / skills / config / docs only)

```
Branch: chore/{short-description}
→ change → commit → short summary → STOP (no issue, no plan-review, no tests, no collection update)
```

### Self-Review Report Format

```markdown
## 🤖 Self-Review Report — Sprint N: {Module}

### Files Changed ({total})
- **Models/Migrations/Enums/DTOs** ({n}): ...
- **Services/Controllers/Requests/Resources** ({n}): ...
- **Events/Listeners/Jobs/Mails** ({n}): ...
- **Tests/Routes/api-collections/Other** ({n}): ...

### Tests
{X} passed, {Y} failed — {T}s

### New Endpoints
- `METHOD /path` — description

### Potential Issues
- **[severity]** description (file:line)

### Pending Dependencies
- ...

### Done Criteria
- [x] Tests pass
- [x] Collection updated (api-collections/) + merge.py run
- [x] Planning doc ✅ Selesai
- [x] DevSeeder updated (if applicable)
- [x] Atomic commits ({N})

**Ready for review.** Run `/push` → `/pr`.
```

---

## API Collections

Format: Postman v2.1 JSON — import into Postman, Bruno, or Insomnia.

```
api-collections/
├── 00-health.collection.json / 00-media.collection.json
├── 01-auth.collection.json … 12-admin.collection.json
├── 07-webhooks.collection.json          ← sub-module, same prefix as parent
├── marketplace_api.collection.json      ← generated by merge.py, DO NOT edit manually
└── marketplace_dev.environment.json     ← single env file for all tools
```

### Scripting Convention

Write all scripts using `pm.*`. Copy this shim **manually** to the top of every new `exec` array — `merge.py` does NOT inject it automatically:

```javascript
// Shim — copy manually to top of each exec array
if (typeof bru !== 'undefined' && typeof pm === 'undefined') {
  var pm = {
    response: { code: res.getStatus(), json: () => res.getBody(), text: () => res.getBody(),
                to: { have: { status: (n) => { expect(res.getStatus()).to.equal(n); } } } },
    collectionVariables: { set: (k,v) => bru.setVar(k,v), get: (k) => bru.getVar(k) },
    environment: { set: (k,v) => bru.setEnvVar(k,v), get: (k) => bru.getEnvVar(k) },
    test: (name, fn) => test(name, fn),
    expect: (val) => expect(val)
  };
}
```

### Update Workflow (mandatory every sprint)

```bash
# 1. Edit: api-collections/{nn}-{domain}.collection.json
# 2. Regenerate master:
python3 api-collections/merge.py
# 3. Commit both:
git add api-collections/{nn}-{domain}.collection.json api-collections/marketplace_api.collection.json
git commit -m "docs(api-collections): update {domain} collection"
```

### Rules
1. One file per domain: `{nn}-{domain}.collection.json` — prefix matches module number exactly.
2. Never edit `marketplace_api.collection.json` manually.
3. Single environment file: `marketplace_dev.environment.json`.
4. Each collection includes variable fallbacks: `base_url`, `access_token`, `refresh_token`.
5. Auth: Bearer `{{access_token}}` at collection level.
6. Save example responses (Success + Error) per request.
7. Scribe (optional, Sprint 8+): `knuckleswtf/scribe` for auto HTML docs from Controller DocBlocks.
