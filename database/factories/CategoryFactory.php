<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'parent_id'  => null,
            'name'       => ucfirst($name),
            'slug'       => Str::slug($name) . '-' . $this->faker->unique()->numerify('##'),
            'icon'       => null,
            'level'      => 1,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function child(Category $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'level'     => $parent->level + 1,
        ]);
    }
}
