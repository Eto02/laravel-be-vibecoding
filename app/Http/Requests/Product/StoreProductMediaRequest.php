<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'mime'     => ['required', 'string', 'in:image/jpeg,image/png,image/webp,video/mp4'],
        ];
    }
}
