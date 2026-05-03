---
description: Scaffold an API Resource (and optionally ResourceCollection) with explicit field whitelist following project standards
---

Create an API Resource for model: $ARGUMENTS

Parse the argument as `{ModelName}`. If the argument contains the word "collection", also generate a `ResourceCollection`.

---

## File 1: Resource Class

Path: `app/Http/Resources/{ModelName}/{ModelName}Resource.php`

```php
<?php

namespace App\Http\Resources\{ModelName};

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {ModelName}Resource extends JsonResource
{
    /**
     * Explicit field whitelist — never use parent::toArray().
     * Rules:
     * - Dates: $this->created_at?->toISOString()
     * - Enums: $this->status->value
     * - Relations: $this->whenLoaded('relation', fn() => new RelatedResource($this->relation))
     * - Money: expose both cents int AND formatted string
     * - Never expose: password, remember_token, pivot internal fields
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            // List every field the API consumer needs.
            // Remove anything sensitive.
            // For monetary fields:
            //   'price_cents' => $this->price,
            //   'price'       => number_format($this->price / 100, 2),
            // For enum fields:
            //   'status' => $this->status->value,
            // For conditionally loaded relations:
            //   'user' => $this->whenLoaded('user', fn() => new \App\Http\Resources\User\UserResource($this->user)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

---

## File 2 (conditional): ResourceCollection

Only generate if "collection" is in the argument. Path: `app/Http/Resources/{ModelName}/{ModelName}Collection.php`

Use a `ResourceCollection` only when you need custom `with()` data. For simple collection wrapping, prefer `{ModelName}Resource::collection($items)` instead.

```php
<?php

namespace App\Http\Resources\{ModelName};

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class {ModelName}Collection extends ResourceCollection
{
    public $collects = {ModelName}Resource::class;

    public function with(Request $request): array
    {
        return [
            // Additional top-level keys alongside 'data' and 'links'
            // e.g. 'stats' => [...],
        ];
    }
}
```

---

## Rules

- `toArray()` must be an explicit whitelist — never spread model attributes
- Null-safe the date accessor: `$this->created_at?->toISOString()` not `$this->created_at->toISOString()`
- Use `$this->whenLoaded()` for relations — never eager-load inside a Resource method
- Do not include `success`, `message`, or `meta` inside the Resource — those belong to `ApiResponse`
- Enum fields: always expose `->value`, not the Enum object itself
- Monetary amounts: always provide both integer cents and human-formatted decimal
- After generating, remind: to use this resource in a controller return `new {ModelName}Resource($model)` or `{ModelName}Resource::collection($paginator)->resolve()` (for paginated responses passed to `ApiResponse::success`)
