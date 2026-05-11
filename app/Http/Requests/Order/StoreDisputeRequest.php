<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason'      => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }
}
