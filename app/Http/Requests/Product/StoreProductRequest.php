<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'weight_gram' => ['nullable', 'integer', 'min:1', 'max:30000'],
        ];
    }
}
