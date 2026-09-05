<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory()->published(),
            'product_variant_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
            // Cents: KES 500 to KES 500,000, the same band ProductFactory prices in.
            'unit_price_cents' => fake()->numberBetween(50_000, 50_000_000),
        ];
    }

    public function forProduct(Product $product, int $quantity = 1): static
    {
        return $this->state(fn () => [
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
            'unit_price_cents' => $product->effectivePriceCents() ?? 0,
        ]);
    }
}
