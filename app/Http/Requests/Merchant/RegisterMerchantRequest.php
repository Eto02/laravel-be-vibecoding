<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'city'        => ['required', 'string', 'max:100'],
            'province'    => ['required', 'string', 'max:100'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
