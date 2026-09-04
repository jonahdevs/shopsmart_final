<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // fake()->words() is typed array|string, so build from word() like
            // the sibling factories do — it is unambiguously a string.
            'headline' => ucfirst(fake()->word().' '.fake()->word().' '.fake()->word()),
            'subheadline' => fake()->sentence(),
            'cta_label' => 'Shop now',
            'cta_url' => '/products',
            'alignment' => fake()->randomElement(['left', 'center', 'right']),
            'text_theme' => fake()->randomElement(['dark', 'light']),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /** A campaign slide with an explicit window. */
    public function scheduled(DateTimeInterface $from, DateTimeInterface $to): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'starts_at' => $from,
            'ends_at' => $to,
        ]);
    }

    /** Still active, but its window has already closed. */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
