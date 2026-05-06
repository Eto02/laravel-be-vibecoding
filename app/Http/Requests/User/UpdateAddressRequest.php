<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'          => ['sometimes', 'string', 'max:50'],
            'recipient_name' => ['sometimes', 'string', 'max:255'],
            'phone'          => ['sometimes', 'string', 'max:20'],
            'province'       => ['sometimes', 'string', 'max:100'],
            'city'           => ['sometimes', 'string', 'max:100'],
            'district'       => ['sometimes', 'string', 'max:100'],
            'postal_code'    => ['sometimes', 'string', 'max:10'],
            'street'         => ['sometimes', 'string', 'max:500'],
            'lat'            => ['nullable', 'numeric', 'between:-90,90'],
            'lng'            => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
