<?php

namespace App\DTOs\Product;

use App\Http\Requests\Product\StoreProductRequest;

readonly class CreateProductDTO
{
    public function __construct(
        public int $storeId,
        public int $categoryId,
        public string $name,
        public string $description,
        public ?int $weightGram,
    ) {}

    public static function fromRequest(StoreProductRequest $request, int $storeId): self
    {
        return new self(
            storeId: $storeId,
            categoryId: $request->integer('category_id'),
            name: $request->string('name')->toString(),
            description: $request->string('description')->toString(),
            weightGram: $request->filled('weight_gram') ? $request->integer('weight_gram') : null,
        );
    }
}
