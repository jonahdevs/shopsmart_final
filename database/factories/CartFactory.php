<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'last_activity_at' => now(),
        ];
    }

    /** A cart nobody has touched for a while, for abandoned-cart coverage. */
    public function idleSince(int $days): static
    {
        return $this->state(fn () => ['last_activity_at' => now()->subDays($days)]);
    }
}
