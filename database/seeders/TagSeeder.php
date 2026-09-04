<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

/**
 * The merchandising tags the home page rails key off, and the products carried
 * on each.
 *
 * Tags are created without a type so `Product::withAnyTags(['Featured'])`
 * finds them with no extra argument. Membership is derived from stable product
 * data (id and sale price) rather than sampled at random, so re-seeding does
 * not reshuffle the home page.
 */
class TagSeeder extends Seeder
{
    /** Hand-picked highlights for the home page hero grid. */
    private const FEATURED = 'Featured';

    /** Recent additions to the catalog. */
    private const NEW_ARRIVAL = 'New Arrival';

    /** Discounted stock being cleared. */
    private const CLEARANCE = 'Clearance';

    public function run(): void
    {
        $featured = $this->tag(self::FEATURED);
        $newArrival = $this->tag(self::NEW_ARRIVAL);
        $clearance = $this->tag(self::CLEARANCE);

        $sellable = Product::query()
            ->where('status', ProductStatus::Published)
            ->where('stock_status', '!=', StockStatus::OutOfStock)
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->orderBy('id')
            ->get();

        $counts = [self::FEATURED => 0, self::NEW_ARRIVAL => 0, self::CLEARANCE => 0];

        foreach ($sellable as $product) {
            if ($product->id % 9 === 0) {
                $product->attachTag($featured);
                $counts[self::FEATURED]++;
            }

            if ($product->id % 11 === 3) {
                $product->attachTag($newArrival);
                $counts[self::NEW_ARRIVAL]++;
            }

            if ($product->sale_price !== null) {
                $product->attachTag($clearance);
                $counts[self::CLEARANCE]++;
            }
        }

        $this->command->info(sprintf(
            'Tagged %d Featured, %d New Arrival and %d Clearance products.',
            $counts[self::FEATURED],
            $counts[self::NEW_ARRIVAL],
            $counts[self::CLEARANCE],
        ));
    }

    /**
     * Find or create one tag.
     *
     * The slug is written explicitly rather than left to the package's `saving`
     * hook, because DatabaseSeeder seeds with model events muted and the row
     * would otherwise be inserted without one.
     */
    private function tag(string $name): Tag
    {
        $existing = Tag::findFromString($name);

        if ($existing instanceof Tag) {
            return $existing;
        }

        $locale = Tag::getLocale();

        return Tag::create([
            'name' => [$locale => $name],
            'slug' => [$locale => Str::slug($name)],
            'type' => null,
            'order_column' => (int) Tag::query()->max('order_column') + 1,
        ]);
    }
}
