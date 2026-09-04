<?php

namespace Database\Factories;

use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\CategoryPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryPlacement>
 */
class CategoryPlacementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'location' => CategorySection::Navbar,
            'sort_order' => 0,
            'status' => CategoryStatus::Active,
        ];
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_id' => $category->id,
        ]);
    }

    public function location(CategorySection $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'location' => $location,
        ]);
    }

    public function sortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CategoryStatus::Active,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CategoryStatus::Draft,
        ]);
    }
}
