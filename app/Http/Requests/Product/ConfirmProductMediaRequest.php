<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key'  => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'in:image,video'],
        ];
    }
}
