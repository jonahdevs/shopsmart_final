<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Models\Product;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // fake()->words() is typed array|string, so build from word() like the
        // sibling factories do — it is unambiguously a string.
        $name = Str::title(fake()->word().' '.fake()->word().' '.fake()->word());

        return [
            'name' => $name,
            // Suffixed because two different word triples can slugify the same.
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            // Cents: KES 500 to KES 500,000.
            'price' => fake()->numberBetween(50_000, 50_000_000),
            'stock_status' => StockStatus::InStock,
            'stock_quantity' => fake()->numberBetween(1, 500),
            'visibility' => ProductVisibility::Visible,
            'is_taxable' => true,
            'requires_shipping' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function scheduled(DateTimeInterface $publishAt): static
    {
        return $this->state(fn () => [
            'status' => ProductStatus::Scheduled,
            'published_at' => $publishAt,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ProductStatus::Archived,
            'published_at' => null,
        ]);
    }

    /**
     * A variable product prices and stocks through its variants, so it carries
     * no SKU, price or stock of its own.
     */
    public function variable(): static
    {
        return $this->state(fn () => [
            'type' => ProductType::Variable,
            'sku' => null,
            'price' => null,
            'stock_quantity' => null,
        ]);
    }

    /**
     * Discount 20% off whatever price the product ends up with. Applied after
     * making rather than as a state, because `create(['price' => ...])` is
     * merged after every state closure has already run — computing this in a
     * state would silently discount the factory's random default instead.
     */
    public function onSale(): static
    {
        return $this->afterMaking(function (Product $product) {
            $product->sale_price = (int) round(($product->price ?? 100_000) * 0.8);
        });
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock_status' => StockStatus::OutOfStock,
            'stock_quantity' => 0,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['visibility' => ProductVisibility::Hidden]);
    }

    /** Price-on-application: shown as "Request a quote" rather than a price. */
    public function withoutPrice(): static
    {
        return $this->state(fn () => ['price' => null, 'sale_price' => null]);
    }
}
