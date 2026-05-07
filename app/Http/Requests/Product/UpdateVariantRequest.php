<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('variant')?->id;

        return [
            'sku'         => ['sometimes', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'price'       => ['sometimes', 'integer', 'min:1'],
            'stock'       => ['sometimes', 'integer', 'min:0'],
            'weight_gram' => ['nullable', 'integer', 'min:1', 'max:30000'],
            'attributes'  => ['nullable', 'array'],
        ];
    }
}
