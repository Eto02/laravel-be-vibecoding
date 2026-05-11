<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            '_idempotency_key' => $this->header('X-Idempotency-Key'),
        ]);
    }

    public function rules(): array
    {
        return [
            '_idempotency_key'         => ['required', 'string', 'min:8', 'max:128'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.store_id'         => ['required', 'integer', 'exists:stores,id'],
            'items.*.address_id'       => ['required', 'integer', 'exists:addresses,id'],
            'items.*.shipping_courier' => ['required', 'string', 'max:50'],
            'items.*.shipping_service' => ['required', 'string', 'max:50'],
            'items.*.shipping_fee'     => ['required', 'integer', 'min:0'],
            'items.*.notes'            => ['nullable', 'string', 'max:500'],
            'voucher_code'             => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            '_idempotency_key.required' => 'The X-Idempotency-Key header is required.',
        ];
    }
}
