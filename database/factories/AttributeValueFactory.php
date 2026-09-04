<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    /**
     * Slugs are unique per attribute; the counter keeps them unique globally,
     * which satisfies the narrower constraint too.
     */
    protected static int $slugSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = fake()->word();

        return [
            'attribute_id' => Attribute::factory(),
            'value' => $value,
            'label' => ucfirst($value),
            'slug' => Str::slug($value).'-'.(++static::$slugSequence),
            'description' => null,
            'color_code' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function forAttribute(Attribute $attribute): static
    {
        return $this->state(fn (array $attributes): array => [
            'attribute_id' => $attribute->id,
        ]);
    }

    public function sortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * A swatch value, for attributes of type color.
     */
    public function swatch(?string $colorCode = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'color_code' => $colorCode ?? fake()->hexColor(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
