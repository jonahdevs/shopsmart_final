<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->numberBetween(50_000, 5_000_000);
        $subtotal = $unitPrice * $quantity;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->published(),
            'product_variant_id' => null,
            'name' => fake()->words(3, true),
            'sku' => Str::upper(fake()->bothify('??-####')),
            'option_label' => null,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'tax_rate' => 16,
            'tax_cents' => (int) round($subtotal - ($subtotal / 1.16)),
            'total_cents' => $subtotal,
            'product_snapshot' => [],
        ];
    }

    /**
     * A line that sold the given product, priced from the catalog and carrying
     * the snapshot placement would have written.
     */
    public function forProduct(Product $product, int $quantity = 1, ?ProductVariant $variant = null): static
    {
        return $this->state(function (array $attributes) use ($product, $quantity, $variant): array {
            $unitPrice = $variant?->effectivePriceCents() ?? $product->effectivePriceCents() ?? 0;
            $subtotal = $unitPrice * $quantity;

            return [
                'product_id' => $product->getKey(),
                'product_variant_id' => $variant?->getKey(),
                'name' => $product->name,
                'sku' => $variant->sku ?? $product->sku,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'subtotal_cents' => $subtotal,
                'total_cents' => $subtotal,
                'product_snapshot' => ['slug' => $product->slug],
            ];
        });
    }
}
