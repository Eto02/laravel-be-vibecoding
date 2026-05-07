<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name'       => ['sometimes', 'string', 'max:100'],
            'slug'       => ['sometimes', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'icon'       => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
