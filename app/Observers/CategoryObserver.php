<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\StorefrontCache;

/**
 * A category carries its name and slug into the navigation, and its place in
 * the tree into every subtree count, so both cached read models are rebuilt
 * when either changes.
 */
class CategoryObserver
{
    /** @var list<string> */
    private const NAVIGATION_ATTRIBUTES = ['name', 'slug', 'status'];

    /** @var list<string> */
    private const COUNTED_ATTRIBUTES = ['parent_id', 'status'];

    public function __construct(private StorefrontCache $cache) {}

    public function created(Category $category): void
    {
        $this->cache->forgetNavigation();
        $this->cache->forgetCategoryCounts();
    }

    public function updated(Category $category): void
    {
        if ($category->wasChanged(self::NAVIGATION_ATTRIBUTES)) {
            $this->cache->forgetNavigation();
        }

        if ($category->wasChanged(self::COUNTED_ATTRIBUTES)) {
            $this->cache->forgetCategoryCounts();
        }
    }

    public function deleted(Category $category): void
    {
        $this->cache->forgetNavigation();
        $this->cache->forgetCategoryCounts();
    }
}
