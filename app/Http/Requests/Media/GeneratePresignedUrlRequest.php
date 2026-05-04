<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePresignedUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder'   => ['required', 'string', 'in:avatars,store-assets,products,reviews,kyc-documents'],
            'filename' => ['required', 'string', 'max:255'],
            'mime'     => ['required', 'string', 'in:image/jpeg,image/png,image/webp,image/gif,video/mp4,application/pdf'],
        ];
    }
}
