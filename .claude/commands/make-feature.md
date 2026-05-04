---
description: Scaffold a complete marketplace feature — Migration, Model, Factory, Store/Update FormRequests, API Resource, Service, Controller, Domain Route file, and Feature Test (11 files). Uses domain-folder structure as defined in CLAUDE.md.
---

Scaffold a complete marketplace feature for this Laravel 13 API project. The feature name and domain are: $ARGUMENTS

Format expected: `{Domain}/{FeatureName}` — e.g. `Order/OrderItem`, `Product/ProductVariant`, `Merchant/Store`

Follow ALL standards in CLAUDE.md exactly. Use these substitutions:
- `{Domain}` = PascalCase domain folder name (e.g. `Order`)
- `{domain}` = lowercase (e.g. `order`)
- `{Feature}` = PascalCase feature name (e.g. `OrderItem`)
- `{feature}` = camelCase (e.g. `orderItem`)
- `{features}` = snake_case plural (e.g. `order_items`)

---

## File 1: Migration

Path: `database/migrations/{YYYY_MM_DD_HHMMSS}_create_{features}_table.php`

```php
Schema::create('{features}', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    // Domain-specific columns — infer sensible types from the feature name
    // Include a 'status' column with an Enum cast for entities with lifecycle
    $table->string('status')->default('active')->index();
    $table->softDeletes();
    $table->timestamps();
    // Add indexes on all FK columns and 'status'
});
```

---

## File 2: Model

Path: `app/Models/{Feature}.php`

Namespace: `App\Models`

Rules:
- `use HasFactory, SoftDeletes;`
- Explicit `$fillable` (every column except id, timestamps, deleted_at)
- `casts()` with Enum for status if applicable
- Relationships typed (BelongsTo, HasMany, etc.)
- No business logic

---

## File 3: Factory

Path: `database/factories/{Feature}Factory.php`

Namespace: `Database\Factories`

Use `$this->faker` for realistic data. Always include `user_id => User::factory()`.

---

## File 4: Store FormRequest

Path: `app/Http/Requests/{Domain}/Store{Feature}Request.php`

Namespace: `App\Http\Requests\{Domain}`

`authorize()` returns `$this->user() !== null` (or ownership check if applicable).

---

## File 5: Update FormRequest

Path: `app/Http/Requests/{Domain}/Update{Feature}Request.php`

Namespace: `App\Http\Requests\{Domain}`

`authorize()` checks ownership: `$this->route('{feature}')?->user_id === $this->user()->id`

All rules prefixed with `'sometimes|'`.

---

## File 6: API Resource

Path: `app/Http/Resources/{Domain}/{Feature}Resource.php`

Namespace: `App\Http\Resources\{Domain}`

Rules:
- Explicit `toArray()` whitelist — never `parent::toArray()`
- Dates: `$this->created_at?->toISOString()`
- Enums: `$this->status->value`
- Money: expose both `price_cents` and `price` (formatted string)
- Relations: `$this->whenLoaded('relation', fn() => new RelationResource($this->relation))`

---

## File 7: Service

Path: `app/Services/{Domain}/{Feature}Service.php`

Namespace: `App\Services\{Domain}`

```php
<?php

namespace App\Services\{Domain};

use App\Models\{Feature};
use Illuminate\Pagination\LengthAwarePaginator;

class {Feature}Service
{
    public function __construct(
        // Inject Shared services if needed:
        // private readonly \App\Services\Shared\NotificationService $notification,
        // private readonly \App\Services\Shared\MediaService $media,
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return {Feature}::query()
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->latest()
            ->paginate(15);
    }

    public function findById(int $id): {Feature}
    {
        return {Feature}::findOrFail($id);
    }

    public function create(array $data): {Feature}
    {
        return {Feature}::create($data);
    }

    public function update({Feature} ${feature}, array $data): {Feature}
    {
        ${feature}->update($data);
        return ${feature}->fresh();
    }

    public function delete({Feature} ${feature}): bool
    {
        return (bool) ${feature}->delete();
    }
}
```

---

## File 8: Controller

Path: `app/Http/Controllers/Api/{Domain}/{Feature}Controller.php`

Namespace: `App\Http\Controllers\Api\{Domain}`

```php
<?php

namespace App\Http\Controllers\Api\{Domain};

use App\Http\Controllers\Controller;
use App\Http\Requests\{Domain}\Store{Feature}Request;
use App\Http\Requests\{Domain}\Update{Feature}Request;
use App\Http\Resources\{Domain}\{Feature}Resource;
use App\Http\Responses\ApiResponse;
use App\Models\{Feature};
use App\Services\{Domain}\{Feature}Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class {Feature}Controller extends Controller
{
    public function __construct(
        private readonly {Feature}Service ${feature}Service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->{feature}Service->getAll($request->query());
        return ApiResponse::success(
            '{Feature} list retrieved.',
            {Feature}Resource::collection($items)->resolve(),
            200,
            ['pagination' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
                'from'         => $items->firstItem(),
                'to'           => $items->lastItem(),
            ]],
        );
    }

    public function store(Store{Feature}Request $request): JsonResponse
    {
        $item = $this->{feature}Service->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);
        return ApiResponse::success('{Feature} created.', new {Feature}Resource($item), 201);
    }

    public function show({Feature} ${feature}): JsonResponse
    {
        return ApiResponse::success('{Feature} retrieved.', new {Feature}Resource(${feature}));
    }

    public function update(Update{Feature}Request $request, {Feature} ${feature}): JsonResponse
    {
        $item = $this->{feature}Service->update(${feature}, $request->validated());
        return ApiResponse::success('{Feature} updated.', new {Feature}Resource($item));
    }

    public function destroy(Request $request, {Feature} ${feature}): JsonResponse
    {
        if (${feature}->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }
        $this->{feature}Service->delete(${feature});
        return ApiResponse::success('{Feature} deleted.', null, 204);
    }
}
```

---

## File 9: Domain Route File

Path: `routes/api/{domain}.php`

If the file already exists, **append** to it instead of overwriting.

```php
<?php

use App\Http\Controllers\Api\{Domain}\{Feature}Controller;

Route::middleware('auth:sanctum')->prefix('{features}')->name('{domain}.{features}.')->group(function () {
    Route::get('/', [{Feature}Controller::class, 'index'])->name('index');
    Route::post('/', [{Feature}Controller::class, 'store'])->name('store');
    Route::get('/{' . '{feature}' . '}', [{Feature}Controller::class, 'show'])->name('show');
    Route::put('/{' . '{feature}' . '}', [{Feature}Controller::class, 'update'])->name('update');
    Route::delete('/{' . '{feature}' . '}', [{Feature}Controller::class, 'destroy'])->name('destroy');
});
```

Also verify that `routes/api.php` includes this domain file. If not, add:
```php
require __DIR__.'/api/{domain}.php';
```

---

## File 10: Feature Test

Path: `tests/Feature/Api/{Domain}/{Feature}Test.php`

Namespace: `Tests\Feature\Api\{Domain}`

```php
<?php

namespace Tests\Feature\Api\{Domain};

use App\Models\{Feature};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {Feature}Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_cannot_access_{features}(): void
    {
        $this->getJson('/api/{features}')->assertStatus(401);
    }

    public function test_authenticated_user_can_list_{features}(): void
    {
        {Feature}::factory()->count(3)->for($this->user)->create();

        $this->actingAs($this->user)
             ->getJson('/api/{features}')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'success', 'message', 'data',
                 'meta' => ['timestamp', 'pagination' => ['current_page', 'total']],
             ])
             ->assertJson(['success' => true]);
    }

    public function test_authenticated_user_can_create_{feature}(): void
    {
        $payload = []; // Fill with valid required fields

        $this->actingAs($this->user)
             ->postJson('/api/{features}', $payload)
             ->assertStatus(201)
             ->assertJsonStructure(['success', 'message', 'data' => ['id'], 'meta'])
             ->assertJson(['success' => true]);
    }

    public function test_create_{feature}_fails_with_empty_payload(): void
    {
        $this->actingAs($this->user)
             ->postJson('/api/{features}', [])
             ->assertStatus(422)
             ->assertJson(['success' => false]);
    }

    public function test_user_cannot_delete_another_users_{feature}(): void
    {
        $other = User::factory()->create();
        $item  = {Feature}::factory()->for($other)->create();

        $this->actingAs($this->user)
             ->deleteJson("/api/{features}/{$item->id}")
             ->assertStatus(403);
    }
}
```

---

## File 11: Unit Test for Service

Path: `tests/Unit/Services/{Domain}/{Feature}ServiceTest.php`

Namespace: `Tests\Unit\Services\{Domain}`

```php
<?php

namespace Tests\Unit\Services\{Domain};

use App\Services\{Domain}\{Feature}Service;
use PHPUnit\Framework\TestCase;

class {Feature}ServiceTest extends TestCase
{
    private {Feature}Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new {Feature}Service();
    }

    public function test_service_instantiates_correctly(): void
    {
        $this->assertInstanceOf({Feature}Service::class, $this->service);
    }
}
```

---

## Post-Generation Checklist

After generating all 11 files, output:

1. **Files created** — list all with full paths
2. **Assumptions** — any column names or types inferred from the feature name
3. **Route to add** — if `routes/api/{domain}.php` is new, confirm it was added to `routes/api.php`
4. **Migration command** — `docker compose exec app php artisan migrate`
5. **Domain route loader** — confirm `routes/api.php` includes `require __DIR__.'/api/{domain}.php';`
6. **Shared services used** — list any `App\Services\Shared\*` injected into the service
