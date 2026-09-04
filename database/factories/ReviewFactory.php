<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => null,
            'author_name' => fake()->name(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => ReviewStatus::Pending,
            'verified_purchase' => false,
            'approved_at' => null,
        ];
    }

    /**
     * A review that has cleared moderation and is publicly visible.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    /**
     * A review still sitting in the moderation queue.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Pending,
            'approved_at' => null,
        ]);
    }

    /**
     * A review a moderator has turned down.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Rejected,
            'approved_at' => null,
        ]);
    }

    /**
     * A review left by someone who actually bought the product.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_purchase' => true,
        ]);
    }
}
