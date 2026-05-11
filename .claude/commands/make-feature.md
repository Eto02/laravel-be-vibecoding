---
description: Scaffold a complete marketplace feature — Migration, Model, Factory, Store/Update FormRequests, API Resource, Service, Controller, Domain Route file, and Feature Test (11 files). Uses domain-folder structure as defined in CLAUDE.md.
---

Scaffold a complete marketplace feature for this Laravel 13 API project. Feature: $ARGUMENTS

Format expected: `{Domain}/{FeatureName}` — e.g. `Order/OrderItem`, `Product/ProductVariant`.

## Substitutions

- `{Domain}` — PascalCase domain (e.g. `Order`)
- `{domain}` — lowercase (e.g. `order`)
- `{Feature}` — PascalCase feature (e.g. `OrderItem`)
- `{feature}` — camelCase (e.g. `orderItem`)
- `{features}` — snake_case plural (e.g. `order_items`)

## Authoritative Rules

**Read `CLAUDE.md` first.** Apply all of:
- Architecture Rules #1-15 (thin controllers, fat services, DTOs, ApiResponse, etc.)
- Directory Structure (Modular)
- Naming Conventions
- API Resource Rules
- Testing Standards
- Route File Pattern
- Database Strategy (monetary as integer cents, indexes on FK + status + filter cols)

If anything in this command contradicts `CLAUDE.md`, `CLAUDE.md` wins.

## Files to Create (11 total)

| # | Path | Notes |
|---|---|---|
| 1 | `database/migrations/{YYYY_MM_DD_HHMMSS}_create_{features}_table.php` | `foreignId()->constrained()->cascadeOnDelete()` for FKs; index `status`, FK, filter cols; softDeletes; timestamps |
| 2 | `app/Models/{Feature}.php` | `HasFactory, SoftDeletes`; explicit `$fillable`; `casts()` with enums; typed relationships; no business logic |
| 3 | `database/factories/{Feature}Factory.php` | Realistic fake data; named states for common scenarios (e.g. `paid()`, `shipped()`) |
| 4 | `app/Http/Requests/{Domain}/Store{Feature}Request.php` | `authorize(): true`; rules in `rules()`; custom messages if needed; `Rule::enum()` for status fields |
| 5 | `app/Http/Requests/{Domain}/Update{Feature}Request.php` | Same as Store but with `['sometimes', ...]` where appropriate |
| 6 | `app/Http/Resources/{Domain}/{Feature}Resource.php` | Explicit `toArray()` whitelist; `whenLoaded()` for relations; cents + formatted for money; `$this->status->value` for enums |
| 7 | `app/Services/{Domain}/{Feature}Service.php` | Constructor DI for Shared Services; all Eloquent here; throws `DomainException` for business errors; returns Model/DTO/bool |
| 8 | `app/Http/Controllers/Api/{Domain}/{Feature}Controller.php` | Thin: validate (FormRequest) → call Service → return `ApiResponse`. RESTful methods (`index`, `store`, `show`, `update`, `destroy`) |
| 9 | `routes/api/{domain}.php` | Add new resource group, `auth:sanctum` middleware, naming `{domain}.{resource}.{action}` |
| 10 | `tests/Feature/Api/{Domain}/{Feature}Test.php` | `RefreshDatabase`; happy path + at least one failure (auth, 422, 404); `assertJsonStructure`, explicit status code, factories only |
| 11 | `database/seeders/DevSeeder.php` (update) | Add sample data for `test@example.com` if entity is user-visible |

## Constraints

- **All monetary fields:** integer cents (e.g. Rp 50.000 → `5000000`)
- **All Resources:** explicit `toArray()` — never `parent::toArray($request)`
- **All Service methods with >2 params:** use a `readonly class` DTO from `app/DTOs/{Domain}/`
- **All endpoints that mutate financial/inventory state:** require `X-Idempotency-Key` via `IdempotencyService`
- **IDOR protection:** scope queries via `WHERE user_id = ?` / `WHERE store_id = ?` in Service, NOT Eloquent Policy
- **HTTP status:** 200/201 for success, 204 for delete-no-content, 422 for validation, 404 for missing resource, 401 unauth, 403 forbidden
- **No `response()->json()` in Services.** Throw exceptions; let global handler in `bootstrap/app.php` map them.

## Workflow

1. Read `CLAUDE.md` and `planning/NN-{domain}.md` if the planning doc exists for this domain.
2. Generate all 11 files in the order above.
3. Run `docker compose exec app php artisan migrate` to apply the migration.
4. Run `docker compose exec app php artisan test --filter={Feature}Test` to verify the feature test passes.
5. Stop — report files created and test result. Do NOT push or create commits unless explicitly asked.

## Output

After scaffolding, emit a brief summary:
- List of files created
- Test result (pass/fail)
- Any TODOs left for the user (e.g. wire up to existing service, add seeder data, decide on enum values)
