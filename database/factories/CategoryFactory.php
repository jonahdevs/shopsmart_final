<?php

namespace Database\Factories;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Guarantees unique slugs across bulk creation without leaning on Faker's
     * unique() pool, which exhausts.
     */
    protected static int $slugSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucwords(fake()->word().' '.fake()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.(++static::$slugSequence),
            'parent_id' => null,
            'description' => fake()->sentence(),
            'icon_svg' => null,
            'status' => CategoryStatus::Active,
            'sort_order' => fake()->numberBetween(0, 100),
            'meta_title' => null,
            'meta_description' => null,
        ];
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

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CategoryStatus::Inactive,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CategoryStatus::Archived,
        ]);
    }

    /**
     * Hang this category off an existing one.
     */
    public function child(Category $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent->id,
        ]);
    }
}
