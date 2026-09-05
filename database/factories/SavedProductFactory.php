<?php

namespace Database\Factories;

use App\Enums\SavedProductList;
use App\Models\Product;
use App\Models\SavedProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedProduct>
 */
class SavedProductFactory extends Factory
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
            'product_id' => Product::factory()->published(),
            'list' => SavedProductList::Wishlist,
            'position' => 0,
        ];
    }

    public function compare(): static
    {
        return $this->state(fn () => ['list' => SavedProductList::Compare]);
    }
}
