<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
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
            'label' => fake()->randomElement(['Home', 'Office', null]),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '07'.fake()->numerify('########'),
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret']),
            'county' => fake()->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Uasin Gishu']),
            'postal_code' => fake()->numerify('#####'),
            'country_code' => 'KE',
            'delivery_notes' => null,
            'is_default' => false,
        ];
    }

    public function isDefault(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
