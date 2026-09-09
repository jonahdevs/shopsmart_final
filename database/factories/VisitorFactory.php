<?php

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * Browser and platform are drawn as a matched pair rather than
     * independently: Safari on Windows and Edge on an iPhone do not exist, and
     * a seeded dashboard that shows them teaches the reader to distrust it.
     *
     * @var list<array{browser: string, platform: string}>
     */
    private const CLIENTS = [
        ['browser' => 'Chrome', 'platform' => 'Windows'],
        ['browser' => 'Chrome', 'platform' => 'Android'],
        ['browser' => 'Safari', 'platform' => 'iPhone'],
        ['browser' => 'Safari', 'platform' => 'macOS'],
        ['browser' => 'Edge', 'platform' => 'Windows'],
        ['browser' => 'Firefox', 'platform' => 'Linux'],
        ['browser' => 'Samsung Internet', 'platform' => 'Android'],
    ];

    /**
     * Where a seeded visit came from, weighted by repetition.
     *
     * This is a Kenyan store, so Kenya is most of the traffic, the region it
     * ships to is the next band, and the diaspora markets are the thin tail.
     * The list is long enough to give the map a spread — a world map shaded in
     * four countries reads as a broken map rather than a quiet one — and
     * weighted rather than uniform so the shading ramp has both ends in use.
     *
     * @var list<string>
     */
    private const COUNTRIES = [
        'KE', 'KE', 'KE', 'KE', 'KE', 'KE', 'KE', 'KE', 'KE', 'KE',
        'KE', 'KE', 'KE', 'KE', 'KE', 'KE', 'KE', 'KE',
        'UG', 'UG', 'UG', 'TZ', 'TZ', 'TZ', 'RW', 'RW', 'ET', 'SO',
        'NG', 'NG', 'ZA', 'ZA', 'GH', 'EG',
        'GB', 'GB', 'GB', 'US', 'US', 'US', 'AE', 'AE', 'IN', 'IN',
        'DE', 'CA', 'AU', 'FR', 'NL', 'CN',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $client = fake()->randomElement(self::CLIENTS);

        return [
            'tracking_id' => (string) Str::uuid(),
            'is_new' => fake()->boolean(65),
            'ip' => fake()->ipv4(),
            'browser' => $client['browser'],
            'platform' => $client['platform'],
            'country' => fake()->randomElement(self::COUNTRIES),
        ];
    }

    /** A first visit under this tracking id. */
    public function newVisitor(): static
    {
        return $this->state(fn (): array => ['is_new' => true]);
    }

    /** A visitor who had been here before. */
    public function returning(): static
    {
        return $this->state(fn (): array => ['is_new' => false]);
    }

    /** A session the edge could place in a named country. */
    public function from(string $country): static
    {
        return $this->state(fn (): array => ['country' => $country]);
    }

    /**
     * A session no edge could place — the ordinary case for an application that
     * is not behind a proxy that resolves one.
     */
    public function unplaced(): static
    {
        return $this->state(fn (): array => ['country' => null]);
    }

    /**
     * A session that happened on a particular day. `created_at` is the only
     * clock this table has, so a seeded history has to set it explicitly.
     */
    public function on(\DateTimeInterface $at): static
    {
        return $this->state(fn (): array => [
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
