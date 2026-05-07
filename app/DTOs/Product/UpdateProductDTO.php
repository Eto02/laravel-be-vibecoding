<?php

namespace App\DTOs\Product;

use App\Http\Requests\Product\UpdateProductRequest;

readonly class UpdateProductDTO
{
    public function __construct(
        public int $categoryId,
        public string $name,
        public string $description,
        public ?int $weightGram,
    ) {}

    public static function fromRequest(UpdateProductRequest $request): self
    {
        return new self(
            categoryId: $request->integer('category_id'),
            name: $request->string('name')->toString(),
            description: $request->string('description')->toString(),
            weightGram: $request->filled('weight_gram') ? $request->integer('weight_gram') : null,
        );
    }
}
