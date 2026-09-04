<?php

namespace Database\Factories;

use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaxClass>
 */
class TaxClassFactory extends Factory
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
            'rate' => fake()->randomElement([0, 8, 16]),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * The Kenyan standard VAT band.
     */
    public function standardVat(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Standard VAT',
            'rate' => 16,
        ]);
    }

    public function zeroRated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Zero Rated',
            'rate' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
