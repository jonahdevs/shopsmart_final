<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\StorefrontCache;

/**
 * Keeps the cached category facet counts honest.
 *
 * Only the columns that decide whether a product counts are watched. A price
 * edit or a stock quantity tick does not change any total, and clearing on
 * every save would leave the cache permanently cold in a store that syncs
 * inventory.
 */
class ProductObserver
{
    /** @var list<string> */
    private const COUNTED_ATTRIBUTES = [
        'status', 'visibility', 'stock_status', 'primary_category_id', 'published_at',
    ];

    public function __construct(private StorefrontCache $cache) {}

    public function created(Product $product): void
    {
        $this->cache->forgetCategoryCounts();
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged(self::COUNTED_ATTRIBUTES)) {
            $this->cache->forgetCategoryCounts();
        }
    }

    public function deleted(Product $product): void
    {
        $this->cache->forgetCategoryCounts();
    }

    public function restored(Product $product): void
    {
        $this->cache->forgetCategoryCounts();
    }
}
