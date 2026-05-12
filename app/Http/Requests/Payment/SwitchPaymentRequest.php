<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway'              => ['required', 'string', Rule::in(['xendit', 'midtrans'])],
            'method'               => ['required', 'string', Rule::in(['invoice', 'virtual_account', 'qris', 'ewallet', 'snap'])],
            'bank_code'            => ['nullable', 'string', 'required_if:method,virtual_account'],
            'ewallet_type'         => ['nullable', 'string', 'required_if:method,ewallet', Rule::in(['OVO', 'DANA', 'GOPAY', 'SHOPEEPAY', 'LINKAJA'])],
            'phone'                => ['nullable', 'string', 'required_if:ewallet_type,OVO'],
            'success_redirect_url' => ['nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_code.required_if'    => 'Bank code is required for virtual account payments.',
            'ewallet_type.required_if' => 'E-wallet type is required for e-wallet payments.',
            'phone.required_if'        => 'Phone number is required for OVO payments.',
        ];
    }
}
