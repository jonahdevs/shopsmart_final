<?php

namespace App\Models;

use App\Enums\ProductLinkType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Settings\InventorySettings;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $sku
 * @property int|null $brand_id
 * @property int|null $primary_category_id
 * @property string|null $model_number
 * @property ProductType $type
 * @property ProductStatus $status
 * @property Carbon|null $published_at
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $technical_specification
 * @property int|null $price
 * @property int|null $sale_price
 * @property int|null $cost_price
 * @property bool $is_taxable
 * @property int|null $tax_class_id
 * @property bool $is_virtual
 * @property bool $requires_shipping
 * @property string|null $weight
 * @property string|null $weight_unit
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property string|null $dimension_unit
 * @property StockStatus $stock_status
 * @property int|null $stock_quantity
 * @property bool $allow_backorder
 * @property int|null $low_stock_threshold
 * @property int|null $min_order_quantity
 * @property ProductVisibility $visibility
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property int $sort_order
 * @property int|null $default_variant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Brand|null $brand
 * @property-read Category|null $primaryCategory
 * @property-read TaxClass|null $taxClass
 * @property-read ProductVariant|null $defaultVariant
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductAttribute> $productAttributes
 * @property-read Collection<int, ProductLink> $links
 * @property-read Collection<int, Review> $reviews
 * @property-read Collection<int, Review> $approvedReviews
 * @property-read Collection<int, ProductView> $views
 * @property-read Collection<int, Product> $upsells
 * @property-read Collection<int, Product> $crossSells
 * @property-read Collection<int, Product> $accessories
 * @property-read Collection<int, Product> $spareParts
 * @property-read int|null $reviews_count
 * @property-read float|null $reviews_avg_rating
 */
#[Fillable([
    'name', 'slug', 'sku', 'brand_id', 'primary_category_id', 'model_number',
    'type', 'status', 'published_at', 'short_description', 'description',
    'technical_specification', 'price', 'sale_price', 'cost_price', 'is_taxable',
    'tax_class_id', 'is_virtual', 'requires_shipping', 'weight', 'weight_unit',
    'length', 'width', 'height', 'dimension_unit', 'stock_status', 'stock_quantity',
    'allow_backorder', 'low_stock_threshold', 'min_order_quantity', 'visibility',
    'meta_title', 'meta_description', 'canonical_url', 'sort_order', 'default_variant_id',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTags, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'stock_status' => StockStatus::class,
            'visibility' => ProductVisibility::class,
            'published_at' => 'datetime',
            'is_taxable' => 'boolean',
            'is_virtual' => 'boolean',
            'requires_shipping' => 'boolean',
            'allow_backorder' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ==================================================
    // SEARCH
    // ==================================================

    /**
     * Columns the Scout database engine searches. That engine queries the real
     * table, so every key here must be an actual column — a brand name match is
     * handled by the catalog query instead.
     *
     * SKU and model number are prefix matches ("EX-40" should find "EX-4000"
     * but not every SKU containing those characters). Everything else uses the
     * default wildcard LIKE, which behaves identically on MySQL and on the
     * SQLite the test suite runs against — a full-text index would not.
     *
     * @return array<string, mixed>
     */
    #[SearchUsingPrefix(['sku', 'model_number'])]
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'model_number' => $this->model_number,
            'short_description' => $this->short_description,
        ];
    }

    /** Draft and archived products must never surface in search results. */
    public function shouldBeSearchable(): bool
    {
        return $this->isPublished();
    }

    // ==================================================
    // MEDIA
    // ==================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Conversions default to JPG, which has no alpha channel, so a
        // transparent source PNG would otherwise flatten onto black. Flattening
        // onto white keeps every rendition consistent with the white image
        // containers used across the storefront.
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
            ->background('ffffff')
            ->fit(Fit::Crop, 120, 120);

        $this->addMediaConversion('card')
            ->performOnCollections('images')
            ->background('ffffff')
            ->fit(Fit::Crop, 600, 600);

        $this->addMediaConversion('card-webp')
            ->performOnCollections('images')
            ->background('ffffff')
            ->fit(Fit::Crop, 600, 600)
            ->format('webp')
            ->quality(85);

        $this->addMediaConversion('zoom')
            ->performOnCollections('images')
            ->background('ffffff')
            ->fit(Fit::Max, 1200, 1200);

        $this->addMediaConversion('zoom-webp')
            ->performOnCollections('images')
            ->background('ffffff')
            ->fit(Fit::Max, 1200, 1200)
            ->format('webp')
            ->quality(85);

        // Blur-up placeholder shown while the real image loads.
        $this->addMediaConversion('lqip')
            ->performOnCollections('images')
            ->background('ffffff')
            ->width(64)
            ->quality(20);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'sku', 'status', 'visibility', 'price', 'sale_price', 'stock_quantity', 'stock_status'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('product');
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    /** @return BelongsTo<TaxClass, $this> */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('sort_order');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'default_variant_id');
    }

    /** @return HasMany<ProductAttribute, $this> */
    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * Every curated link row owned by this product, ordered for editing.
     *
     * @return HasMany<ProductLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ProductLink::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<Product, $this> */
    public function upsells(): BelongsToMany
    {
        return $this->linkedProductsOfType(ProductLinkType::Upsell);
    }

    /** @return BelongsToMany<Product, $this> */
    public function crossSells(): BelongsToMany
    {
        return $this->linkedProductsOfType(ProductLinkType::CrossSell);
    }

    /** @return BelongsToMany<Product, $this> */
    public function accessories(): BelongsToMany
    {
        return $this->linkedProductsOfType(ProductLinkType::Accessory);
    }

    /** @return BelongsToMany<Product, $this> */
    public function spareParts(): BelongsToMany
    {
        return $this->linkedProductsOfType(ProductLinkType::SparePart);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Deliberately unordered: this relation is also aggregated by the
     * withReviewStats() scope, and a listing subquery has no use for an ORDER BY.
     * Callers that display reviews add their own ordering.
     *
     * @return HasMany<Review, $this>
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->approved();
    }

    /** @return HasMany<ProductView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(ProductView::class);
    }

    /** @return BelongsToMany<Product, $this> */
    private function linkedProductsOfType(ProductLinkType $type): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_links', 'product_id', 'linked_product_id')
            ->wherePivot('type', $type->value)
            ->withPivot('type', 'is_required', 'default_quantity', 'sort_order')
            ->orderByPivot('sort_order');
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Products that are live right now: explicitly published, or scheduled with
     * a publish time that has already passed.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where(function (Builder $live) {
            $live->where('status', ProductStatus::Published)
                ->orWhere(fn (Builder $scheduled) => $scheduled
                    ->where('status', ProductStatus::Scheduled)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()));
        });
    }

    /**
     * Products that appear in catalog and category listings.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visibleInCatalog(Builder $query): void
    {
        $query->whereIn('visibility', [ProductVisibility::Visible, ProductVisibility::Catalog]);
    }

    /**
     * Products that appear in search results.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visibleInSearch(Builder $query): void
    {
        $query->whereIn('visibility', [ProductVisibility::Visible, ProductVisibility::Search]);
    }

    /**
     * Apply the store-wide out-of-stock display rule from InventorySettings.
     * When set to "hide", out-of-stock products drop out of storefront
     * listings; in-stock and backorderable products are unaffected.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function honorStockVisibility(Builder $query): void
    {
        if (app(InventorySettings::class)->out_of_stock_behavior === 'hide') {
            $query->where('stock_status', '!=', StockStatus::OutOfStock);
        }
    }

    /**
     * Select approved-review count and average rating as `reviews_count` and
     * `reviews_avg_rating`, so a listing renders star ratings without a query
     * per product. Aggregates through the `approvedReviews` relation so
     * Review::approved() stays the single definition of "approved".
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withReviewStats(Builder $query): void
    {
        $query
            ->withCount('approvedReviews as reviews_count')
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating');
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /**
     * Load the review aggregates unless a listing query already selected them
     * via the withReviewStats() scope. A caller that forgets the scope then
     * pays one extra query instead of silently rendering the product as unrated.
     */
    public function loadReviewStatsIfMissing(): static
    {
        if (! array_key_exists('reviews_count', $this->getAttributes())) {
            $this->loadCount('approvedReviews as reviews_count')
                ->loadAvg('approvedReviews as reviews_avg_rating', 'rating');
        }

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published
            || ($this->status === ProductStatus::Scheduled
                && $this->published_at !== null
                && $this->published_at->isPast());
    }

    /** The price a customer actually pays, in cents. Null means price-on-application. */
    public function effectivePriceCents(): ?int
    {
        return $this->sale_price ?? $this->price;
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null
            && $this->price !== null
            && $this->sale_price < $this->price;
    }

    /** Whole-percent saving off the original price, or null when not on sale. */
    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || $this->price === 0) {
            return null;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    public function isInStock(): bool
    {
        return $this->stock_status !== StockStatus::OutOfStock;
    }

    /** Variable products price and stock through their variants, not their own columns. */
    public function hasVariants(): bool
    {
        return $this->type === ProductType::Variable;
    }
}
