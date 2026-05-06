<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'     => ['required', Rule::in(['ktp', 'npwp', 'siup'])],
            'filename' => ['required', 'string', 'max:255'],
            'mime'     => ['required', Rule::in(['image/jpeg', 'image/png', 'application/pdf'])],
        ];
    }
}
