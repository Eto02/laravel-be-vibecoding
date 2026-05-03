<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'integer', 'min:10000'],
            'description' => ['nullable', 'string', 'max:255'],
            'currency'    => ['nullable', 'string', 'in:IDR'],
        ];
    }
}
