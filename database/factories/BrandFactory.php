<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
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
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.(++static::$slugSequence),
            'description' => fake()->paragraph(),
            'website_url' => fake()->url(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
