<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** Keeps order numbers unique across bulk creation without Faker's unique() pool. */
    protected static int $numberSequence = 0;

    /**
     * Define the model's default state.
     *
     * A pending, unpaid delivery order with no lines. Totals are internally
     * consistent so a test that does not care about money still gets numbers
     * that add up.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100_000, 5_000_000);
        $shipping = 30_000;

        return [
            'order_number' => 'SS-'.str_pad((string) (++static::$numberSequence), 6, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '07'.fake()->numerify('########'),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => null,
            'currency' => 'KES',
            'prices_include_tax' => true,
            'subtotal_cents' => $subtotal,
            'discount_cents' => 0,
            'shipping_cents' => $shipping,
            'tax_cents' => (int) round($subtotal - ($subtotal / 1.16)),
            'total_cents' => $subtotal + $shipping,
            'coupon_id' => null,
            'coupon_code' => null,
            'delivery_method' => DeliveryMethod::Delivery,
            'shipping_address_id' => null,
            'shipping_first_name' => fake()->firstName(),
            'shipping_last_name' => fake()->lastName(),
            'shipping_phone' => '07'.fake()->numerify('########'),
            'shipping_line1' => fake()->streetAddress(),
            'shipping_line2' => null,
            'shipping_city' => 'Nairobi',
            'shipping_county' => 'Nairobi',
            'shipping_postal_code' => fake()->numerify('#####'),
            'shipping_country_code' => 'KE',
            'customer_note' => null,
            'staff_note' => null,
            'placed_at' => now(),
            'paid_at' => null,
            'cancelled_at' => null,
            'stock_deducted_at' => null,
        ];
    }

    /**
     * Settled. Note this sets the columns directly rather than going through
     * {@see Order::markPaid()}, so it moves no stock and redeems no coupon —
     * a test that wants those side effects must call the method.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Success,
            'payment_method' => 'paystack',
            'paid_at' => now(),
            'stock_deducted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_method' => DeliveryMethod::Pickup,
            'shipping_cents' => 0,
            'shipping_first_name' => null,
            'shipping_last_name' => null,
            'shipping_phone' => null,
            'shipping_line1' => null,
            'shipping_line2' => null,
            'shipping_city' => null,
            'shipping_county' => null,
            'shipping_postal_code' => null,
            'shipping_country_code' => null,
        ]);
    }
}
