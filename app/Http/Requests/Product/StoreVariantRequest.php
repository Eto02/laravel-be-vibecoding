<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'         => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'price'       => ['required', 'integer', 'min:1'],
            'stock'       => ['required', 'integer', 'min:0'],
            'weight_gram' => ['nullable', 'integer', 'min:1', 'max:30000'],
            'attributes'  => ['nullable', 'array'],
        ];
    }
}
