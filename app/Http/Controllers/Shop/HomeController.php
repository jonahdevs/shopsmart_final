<?php

namespace App\Http\Controllers\Shop;

use App\Data\CategoryData;
use App\Data\HeroSlideData;
use App\Data\ProductCardData;
use App\Enums\CategorySection;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;
use App\Models\Category;
use App\Models\CategoryPlacement;
use App\Models\HeroSlide;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use FiltersCatalogProducts;

    /** Tiles in the "shop by category" grid. */
    private const FEATURED_CATEGORY_LIMIT = 15;

    /** Products per home-page rail. */
    private const RAIL_SIZE = 16;

    /** Merchandising tag behind the curated "featured" rail. */
    private const FEATURED_TAG = 'Featured';

    /**
     * Show the storefront home page.
     *
     * The hero and the category grid are the only things above the fold, so
     * they render on the first response; the two product rails are deferred and
     * arrive in a follow-up request rather than holding the hero back. They
     * share the default group, so that follow-up is a single request.
     */
    public function __invoke(): Response
    {
        return Inertia::render('shop/Home', [
            'heroSlides' => $this->heroSlides(),
            'featuredCategories' => $this->featuredCategories(),
            'newArrivals' => Inertia::defer(fn (): array => $this->newArrivals()),
            'featuredProducts' => Inertia::defer(fn (): array => $this->featuredProducts()),
        ]);
    }

    /**
     * @return list<HeroSlideData>
     */
    private function heroSlides(): array
    {
        return array_values(HeroSlide::query()
            ->live()
            ->with('media')
            ->get()
            ->map(fn (HeroSlide $slide): HeroSlideData => HeroSlideData::fromModel($slide))
            ->all());
    }

    /**
     * The categories staff have pinned to the home page, in the order they
     * pinned them.
     *
     * `CategoryPlacement::active()` only speaks for the placement's own status,
     * so a tile survived its category being drafted — and then 404'd on click,
     * because the category page admits nothing but an active category. The
     * category's status is checked here too.
     *
     * @return list<CategoryData>
     */
    private function featuredCategories(): array
    {
        $productIdsByCategory = $this->catalogProductIdsByCategory();

        return array_values(CategoryPlacement::query()
            ->active()
            ->forLocation(CategorySection::HomePageFeatured)
            ->whereHas('category', $this->constrainToActiveCategory(...))
            ->with(['category' => fn (Relation $category) => $category->with('media')])
            ->take(self::FEATURED_CATEGORY_LIMIT)
            ->get()
            ->map(fn (CategoryPlacement $placement): CategoryData => CategoryData::fromModel(
                $placement->category,
                productCount: count($productIdsByCategory[$placement->category_id] ?? []),
            ))
            ->all());
    }

    /**
     * @param  Builder<Category>  $query
     */
    private function constrainToActiveCategory(Builder $query): void
    {
        $query->active();
    }

    /**
     * Curated highlights: products tagged "Featured", falling back to the
     * best-merchandised sellable stock when nothing has been tagged yet.
     *
     * @return list<ProductCardData>
     */
    private function featuredProducts(): array
    {
        $featured = $this->sellableQuery()
            ->whereHas('tags', fn (Builder $tag) => $tag->where('name->'.app()->getLocale(), self::FEATURED_TAG))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->take(self::RAIL_SIZE)
            ->get();

        if ($featured->isEmpty()) {
            $featured = $this->sellableQuery()
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->take(self::RAIL_SIZE)
                ->get();
        }

        return array_values($featured
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }

    /**
     * Recently published sellable stock, newest first.
     *
     * @return list<ProductCardData>
     */
    private function newArrivals(): array
    {
        return array_values($this->sellableQuery()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(self::RAIL_SIZE)
            ->get()
            ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
            ->all());
    }

    /**
     * A home-page rail only ever shows something a shopper can buy right now,
     * so unpriced and out-of-stock products are excluded outright rather than
     * left to the store-wide stock visibility setting.
     *
     * @return Builder<Product>
     */
    private function sellableQuery(): Builder
    {
        return $this->catalogQuery()
            ->where('stock_status', StockStatus::InStock)
            ->whereNotNull('price')
            ->where('price', '>', 0);
    }
}
