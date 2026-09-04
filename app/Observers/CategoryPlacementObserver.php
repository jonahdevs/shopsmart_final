<?php

namespace App\Observers;

use App\Models\CategoryPlacement;
use App\Support\StorefrontCache;

/**
 * A placement is what puts a category in the header stripe, so any change to
 * one — where it points, its order, whether it is active — rebuilds the cached
 * navigation. This is the invalidation HandleInertiaRequests always documented.
 */
class CategoryPlacementObserver
{
    public function __construct(private StorefrontCache $cache) {}

    public function saved(CategoryPlacement $placement): void
    {
        $this->cache->forgetNavigation();
    }

    public function deleted(CategoryPlacement $placement): void
    {
        $this->cache->forgetNavigation();
    }
}
