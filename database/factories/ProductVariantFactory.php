<?php

namespace Database\Factories;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->variable(),
            'sku' => 'VAR-'.fake()->unique()->numerify('######'),
            'price' => fake()->numberBetween(50_000, 50_000_000),
            'stock_status' => StockStatus::InStock,
            'stock_quantity' => fake()->numberBetween(1, 500),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock_status' => StockStatus::OutOfStock,
            'stock_quantity' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Discount 20% off whatever price the variant ends up with. Applied after
     * making rather than as a state, because `create(['price' => ...])` is
     * merged after every state closure has already run — computing this in a
     * state would silently discount the factory's random default instead.
     */
    public function onSale(): static
    {
        return $this->afterMaking(function (ProductVariant $variant) {
            $variant->sale_price = (int) round(($variant->price ?? 100_000) * 0.8);
        });
    }
}
