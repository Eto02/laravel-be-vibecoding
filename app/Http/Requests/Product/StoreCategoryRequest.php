<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100'],
            'slug'       => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'icon'       => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
