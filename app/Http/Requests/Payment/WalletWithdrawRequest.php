<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class WalletWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'          => ['required', 'integer', 'min:1000000'],  // min Rp 10.000
            'bank_code'       => ['required', 'string'],
            'account_number'  => ['required', 'string'],
            'account_name'    => ['required', 'string', 'max:100'],
        ];
    }
}
