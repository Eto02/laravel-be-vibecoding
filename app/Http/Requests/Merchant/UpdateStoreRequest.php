<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'city'        => ['sometimes', 'string', 'max:100'],
            'province'    => ['sometimes', 'string', 'max:100'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'logo'        => ['prohibited'],
            'banner'      => ['prohibited'],
            'slug'        => ['prohibited'],
            'status'      => ['prohibited'],
            'kyc_status'  => ['prohibited'],
        ];
    }
}
