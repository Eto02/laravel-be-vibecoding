---
description: Scaffold a complete marketplace feature — Migration, Model, Factory, Store/Update FormRequests, API Resource, Service, Controller, Route snippet, and Feature Test (10 files)
---

Scaffold a complete marketplace feature for this Laravel 13 API project. The feature name is: $ARGUMENTS

Follow ALL standards in CLAUDE.md exactly. Generate all 10 files. Use these substitutions throughout:
- `{Feature}` = PascalCase (e.g. `Product`)
- `{feature}` = camelCase (e.g. `product`)
- `{features}` = snake_case plural (e.g. `products`)
- `{Feature}s` = plural PascalCase for class plurals

---

## File 1: Migration

Path: `database/migrations/{YYYY_MM_DD_HHMMSS}_create_{features}_table.php`

Use current timestamp. Schema:
```php
Schema::create('{features}', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    // Domain-specific columns — infer sensible types from the feature name
    // Include a 'status' column for entities with lifecycle
    $table->string('status')->default('active');
    $table->softDeletes();
    $table->timestamps();
});
```
Add indexes on every FK column and the `status` column.

---

## File 2: Model

Path: `app/Models/{Feature}.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class {Feature} extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // list every column except id, timestamps, deleted_at
    ];

    protected function casts(): array
    {
        return [
            // 'status' => \App\Enums\{Feature}Status::class,  // if enum applies
            // 'price'  => 'decimal:2',
            // 'meta'   => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## File 3: Factory

Path: `database/factories/{Feature}Factory.php`

```php
<?php

namespace Database\Factories;

use App\Models\{Feature};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class {Feature}Factory extends Factory
{
    protected $model = {Feature}::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // use $this->faker for realistic data matching domain columns
            'status'  => 'active',
        ];
    }
}
```

---

## File 4: Store FormRequest

Path: `app/Http/Requests/{Feature}/Store{Feature}Request.php`

```php
<?php

namespace App\Http\Requests\{Feature};

use Illuminate\Foundation\Http\FormRequest;

class Store{Feature}Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // 'name'  => 'required|string|max:255',
            // 'price' => 'required|integer|min:0',
        ];
    }
}
```

---

## File 5: Update FormRequest

Path: `app/Http/Requests/{Feature}/Update{Feature}Request.php`

```php
<?php

namespace App\Http\Requests\{Feature};

use Illuminate\Foundation\Http\FormRequest;

class Update{Feature}Request extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('{feature}');
        return $record && $record->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            // same as Store but all prefixed with 'sometimes|'
        ];
    }
}
```

---

## File 6: API Resource

Path: `app/Http/Resources/{Feature}/{Feature}Resource.php`

```php
<?php

namespace App\Http\Resources\{Feature};

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {Feature}Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            // Explicit whitelist — list every field the API should expose
            // Never use parent::toArray()
            // Dates: $this->created_at?->toISOString()
            // Enums: $this->status->value
            // Relations: $this->whenLoaded('user', fn() => new \App\Http\Resources\User\UserResource($this->user))
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

---

## File 7: Service

Path: `app/Services/{Feature}Service.php`

```php
<?php

namespace App\Services;

use App\Models\{Feature};
use Illuminate\Pagination\LengthAwarePaginator;

class {Feature}Service
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return {Feature}::query()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
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

Path: `app/Http/Controllers/Api/{Feature}Controller.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{Feature}\Store{Feature}Request;
use App\Http\Requests\{Feature}\Update{Feature}Request;
use App\Http\Resources\{Feature}\{Feature}Resource;
use App\Http\Responses\ApiResponse;
use App\Models\{Feature};
use App\Services\{Feature}Service;
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
        $meta  = [
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
                'from'         => $items->firstItem(),
                'to'           => $items->lastItem(),
            ],
        ];
        return ApiResponse::success(
            '{Feature} list retrieved.',
            {Feature}Resource::collection($items)->resolve(),
            200,
            $meta,
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

    public function destroy({Feature} ${feature}): JsonResponse
    {
        if (${feature}->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }
        $this->{feature}Service->delete(${feature});
        return ApiResponse::success('{Feature} deleted.', null, 204);
    }
}
```

> Note: The `destroy` method receives `$request` — add `Request $request` to its signature if ownership check is needed.

---

## File 9: Route Snippet

Show this snippet to add to `routes/api.php`:

```php
use App\Http\Controllers\Api\{Feature}Controller;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('{features}', {Feature}Controller::class);
});
```

If an `auth:sanctum` group already exists, nest the `apiResource` line inside it.

---

## File 10: Feature Test

Path: `tests/Feature/Api/{Feature}/{Feature}Test.php`

```php
<?php

namespace Tests\Feature\Api\{Feature};

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

    public function test_unauthenticated_user_cannot_list_{features}(): void
    {
        $this->getJson('/api/{features}')
             ->assertStatus(401);
    }

    public function test_authenticated_user_can_list_{features}(): void
    {
        {Feature}::factory()->count(3)->for($this->user)->create();

        $this->actingAs($this->user)
             ->getJson('/api/{features}')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'success', 'message',
                 'data',
                 'meta' => ['timestamp', 'pagination' => ['current_page', 'total']],
             ])
             ->assertJson(['success' => true]);
    }

    public function test_authenticated_user_can_create_{feature}(): void
    {
        $payload = []; // fill with valid fields

        $this->actingAs($this->user)
             ->postJson('/api/{features}', $payload)
             ->assertStatus(201)
             ->assertJsonStructure(['success', 'message', 'data' => ['id'], 'meta'])
             ->assertJson(['success' => true]);
    }

    public function test_create_{feature}_fails_validation_with_empty_payload(): void
    {
        $this->actingAs($this->user)
             ->postJson('/api/{features}', [])
             ->assertStatus(422)
             ->assertJson(['success' => false]);
    }

    public function test_authenticated_user_can_view_own_{feature}(): void
    {
        $item = {Feature}::factory()->for($this->user)->create();

        $this->actingAs($this->user)
             ->getJson("/api/{features}/{$item->id}")
             ->assertStatus(200)
             ->assertJson(['success' => true, 'data' => ['id' => $item->id]]);
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

## Post-Generation Checklist

After generating all files, output:

1. **Files created** — list all 10 with full paths
2. **Assumptions** — any column names or types you inferred from the feature name
3. **Route to add** — the exact `routes/api.php` snippet
4. **Run migration** — `php artisan migrate`
5. **Prerequisites** — flag if `app/Http/Responses/ApiResponse.php` does not yet exist (it must exist before the controller will work)
