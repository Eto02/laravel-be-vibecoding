<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')->where('user_id', $this->user()->id)],
            'gateway'  => ['required', 'string', Rule::in(['xendit', 'midtrans'])],
        ];
    }
}
