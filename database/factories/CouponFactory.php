<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to a fixed KES 500 off with no limits of any kind, so a test
     * that cares about one rule can switch on just that rule.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => CouponType::Fixed,
            'amount_cents' => 50_000,
            'percent' => null,
            'min_subtotal_cents' => 0,
            'max_discount_cents' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'used_count' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
            'description' => null,
        ];
    }

    public function fixed(int $amountCents): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Fixed,
            'amount_cents' => $amountCents,
            'percent' => null,
        ]);
    }

    public function percent(float $percent, ?int $maxDiscountCents = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Percent,
            'amount_cents' => null,
            'percent' => $percent,
            'max_discount_cents' => $maxDiscountCents,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function notYetStarted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addWeek(),
        ]);
    }

    /** Global usage limit already reached. */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit' => 5,
            'used_count' => 5,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function requiringSubtotal(int $cents): static
    {
        return $this->state(fn (array $attributes): array => [
            'min_subtotal_cents' => $cents,
        ]);
    }

    public function oncePerCustomer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'usage_limit_per_user' => 1,
        ]);
    }
}
