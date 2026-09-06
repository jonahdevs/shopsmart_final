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
            // At most ONE review per product may carry the demo account. The
            // rest are anonymous. Attaching the same reviewer to several
            // reviews of one product — which a per-review dice roll happily
            // did — puts the same name twice on a product page, and blocks the
            // "one review per customer per product" rule the account area
            // enforces from ever being expressed as a unique index.
            $attributedTo = fake()->boolean(30) ? $reviewer?->id : null;

            if ($product->id % 2 === 0) {
                $count = fake()->numberBetween(1, 5);
                $attributedIndex = $attributedTo === null ? null : fake()->numberBetween(1, $count);

                foreach (range(1, $count) as $index) {
                    Review::factory()->approved()->create([
                        'product_id' => $product->id,
                        'user_id' => $index === $attributedIndex ? $attributedTo : null,
                        'rating' => fake()->randomElement(self::RATING_POOL),
                        'verified_purchase' => fake()->boolean(60),
                    ]);

                    $approved++;
                }

                // Spent on the approved batch, so the pending one below stays
                // anonymous rather than becoming this product's second review
                // from the same person.
                $attributedTo = null;
            }

            if ($product->id % 17 === 0) {
                $count = fake()->numberBetween(1, 2);
                $attributedIndex = $attributedTo === null ? null : fake()->numberBetween(1, $count);

                foreach (range(1, $count) as $index) {
                    Review::factory()->pending()->create([
                        'product_id' => $product->id,
                        'user_id' => $index === $attributedIndex ? $attributedTo : null,
                        'rating' => fake()->randomElement(self::RATING_POOL),
                    ]);

                    $pending++;
                }
            }
        }

        $this->command->info("Seeded {$approved} approved and {$pending} pending reviews.");
    }
}
