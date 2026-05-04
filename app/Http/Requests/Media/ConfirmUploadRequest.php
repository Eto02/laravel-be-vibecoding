<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:500'],
        ];
    }
}
