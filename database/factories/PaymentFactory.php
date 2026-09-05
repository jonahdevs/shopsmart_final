<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reference' => 'SS-'.Str::upper(Str::random(10)),
            'gateway' => 'paystack',
            'status' => PaymentStatus::Pending,
            'amount_cents' => fake()->numberBetween(100_000, 5_000_000),
            'currency' => 'KES',
            'channel' => null,
            'gateway_reference' => null,
            'authorization_code' => null,
            'failure_reason' => null,
            'payload' => null,
            'paid_at' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Success,
            'channel' => 'card',
            'gateway_reference' => (string) fake()->numberBetween(1_000_000, 9_999_999),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Declined by issuer.',
        ]);
    }
}
