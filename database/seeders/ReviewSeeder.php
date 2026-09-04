<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Customer reviews across the catalog: a spread of approved ones so product
 * pages show real star ratings, plus a handful still awaiting moderation so
 * the admin queue is not empty on a fresh install.
 *
 * Only products with no reviews yet are touched, so re-seeding does not pile
 * more reviews onto the same products.
 */
class ReviewSeeder extends Seeder
{
    /**
     * Ratings weighted towards the top of the scale, the way real review
     * distributions sit, while still leaving enough low scores for the average
     * to mean something.
     *
     * @var list<int>
     */
    private const RATING_POOL = [5, 5, 5, 5, 4, 4, 4, 3, 3, 2, 1];

    public function run(): void
    {
        $reviewer = User::query()->where('email', 'test@example.com')->first();

        $products = Product::query()
            ->where('status', ProductStatus::Published)
            ->whereDoesntHave('reviews')
            ->orderBy('id')
            ->get(['id']);

        $approved = 0;
        $pending = 0;

        foreach ($products as $product) {
            if ($product->id % 2 === 0) {
                foreach (range(1, fake()->numberBetween(1, 5)) as $ignored) {
                    Review::factory()->approved()->create([
                        'product_id' => $product->id,
                        'user_id' => fake()->boolean(30) ? $reviewer?->id : null,
                        'rating' => fake()->randomElement(self::RATING_POOL),
                        'verified_purchase' => fake()->boolean(60),
                    ]);

                    $approved++;
                }
            }

            if ($product->id % 17 === 0) {
                foreach (range(1, fake()->numberBetween(1, 2)) as $ignored) {
                    Review::factory()->pending()->create([
                        'product_id' => $product->id,
                        'user_id' => fake()->boolean(30) ? $reviewer?->id : null,
                        'rating' => fake()->randomElement(self::RATING_POOL),
                    ]);

                    $pending++;
                }
            }
        }

        $this->command->info("Seeded {$approved} approved and {$pending} pending reviews.");
    }
}
