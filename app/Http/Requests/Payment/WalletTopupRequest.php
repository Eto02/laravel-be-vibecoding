<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalletTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'  => ['required', 'integer', 'min:100000'],  // min Rp 1.000
            'gateway' => ['required', 'string', Rule::in(['xendit', 'midtrans'])],
            'method'  => ['required', 'string', Rule::in(['invoice', 'virtual_account', 'qris', 'ewallet', 'snap'])],
        ];
    }
}
