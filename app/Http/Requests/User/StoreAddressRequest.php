<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'          => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone'          => ['required', 'string', 'max:20'],
            'province'       => ['required', 'string', 'max:100'],
            'city'           => ['required', 'string', 'max:100'],
            'district'       => ['required', 'string', 'max:100'],
            'postal_code'    => ['required', 'string', 'max:10'],
            'street'         => ['required', 'string', 'max:500'],
            'lat'            => ['nullable', 'numeric', 'between:-90,90'],
            'lng'            => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
