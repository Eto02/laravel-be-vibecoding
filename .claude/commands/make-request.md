---
description: Scaffold a FormRequest with authorization logic and validation rules for a given domain/action pair
---

Create a FormRequest for: $ARGUMENTS

Parse the argument:
- If it contains a `/` separator (e.g. `Product/Store`): first part = domain, second = action
- If it's a single compound word (e.g. `StoreProduct`): split at the verb boundary (Store/Update/Delete/Show = action prefix, rest = domain)
- If it's just a domain name (e.g. `Product`): create both `StoreProductRequest` and `UpdateProductRequest`

---

## File: FormRequest

Path: `app/Http/Requests/{Domain}/{Action}{Domain}Request.php`

```php
<?php

namespace App\Http\Requests\{Domain};

use Illuminate\Foundation\Http\FormRequest;

class {Action}{Domain}Request extends FormRequest
{
    /**
     * Authorization logic:
     *
     * Public endpoints:    return true;
     * Any auth user:       return $this->user() !== null;
     * Store (own data):    return $this->user() !== null;
     * Update/Delete (own): $model = $this->route('{routeParam}');
     *                      return $model && $model->user_id === $this->user()->id;
     * Admin only:          return $this->user()?->hasRole('admin') ?? false;
     */
    public function authorize(): bool
    {
        // TODO: implement appropriate authorization for this action
        return $this->user() !== null;
    }

    /**
     * Validation rules:
     *
     * Store request:  'required|...' for all required fields
     * Update request: 'sometimes|...' prefix for all fields (all optional)
     *
     * Common rules reference:
     *   'name'        => 'required|string|max:255',
     *   'email'       => 'required|email|unique:users,email',
     *   'price'       => 'required|integer|min:0',
     *   'status'      => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\StatusEnum::class)],
     *   'category_id' => 'required|exists:categories,id',
     *   'images'      => 'nullable|array|max:10',
     *   'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:2048',
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Custom validation error messages (optional — override only what needs a custom message).
     */
    public function messages(): array
    {
        return [
            // 'name.required' => 'Please provide a name.',
        ];
    }

    /**
     * Normalize input before validation runs (optional).
     */
    protected function prepareForValidation(): void
    {
        // Example: $this->merge(['slug' => \Illuminate\Support\Str::slug($this->name)]);
    }
}
```

---

## Rules

- `authorize()` must NEVER unconditionally return `true` for protected/owned resources
- Update/Delete requests: always verify ownership in `authorize()` using the route model binding
- Use `Rule::enum()` for enum-backed status fields
- Use `'exists:{table},{column}'` or `Rule::exists()` for FK references
- File upload rules: always specify `mimes:` and `max:` (KB)
- Never put validation inside a controller — that is legacy; FormRequest is the standard
- After generating, remind: inject the FormRequest into the controller method signature:
  ```php
  public function store(Store{Domain}Request $request): JsonResponse
  ```
  Laravel will auto-resolve and validate before the method body runs.
