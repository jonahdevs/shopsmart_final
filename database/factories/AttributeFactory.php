<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
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
            'type' => AttributeType::Select,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function ofType(AttributeType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    /**
     * A colour-swatch attribute; its values carry hex codes.
     */
    public function color(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Colour',
            'type' => AttributeType::Color,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
