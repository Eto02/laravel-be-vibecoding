<?php

namespace App\DTOs\Merchant;

use App\Http\Requests\Merchant\RegisterMerchantRequest;

readonly class RegisterMerchantDTO
{
    public function __construct(
        public string  $name,
        public string  $description,
        public string  $city,
        public string  $province,
        public ?string $phone = null,
    ) {}

    public static function fromRequest(RegisterMerchantRequest $request): self
    {
        return new self(
            name:        $request->input('name'),
            description: $request->input('description'),
            city:        $request->input('city'),
            province:    $request->input('province'),
            phone:       $request->input('phone'),
        );
    }
}
