<?php

namespace App\Models;

use App\Enums\CategoryStatus;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A node in the catalog tree. Depth is unconstrained; listings filter on the
 * whole subtree via {@see self::descendantIds()}.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property string|null $description
 * @property string|null $icon_svg Inline SVG glyph for nav tiles.
 * @property CategoryStatus $status
 * @property int $sort_order
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, CategoryPlacement> $placements
 * @property-read int|null $placements_count
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @property-read Collection<int, Media> $media
 * @property-read int|null $media_count
 */
#[Fillable(['name', 'slug', 'parent_id', 'description', 'icon_svg', 'status', 'sort_order', 'meta_title', 'meta_description'])]
class Category extends Model implements HasMedia
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CategoryStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * Two distinct image roles, each replaced rather than appended to:
     * - image:  square tile used in grids and menus
     * - banner: wide hero stripe at the top of the category page
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Queued like Product's conversions: generating these inline added ~90s
        // to `migrate:fresh --seed`, and there is no scenario where category
        // tiles being ready helps while product images are still pending.
        $this->addMediaConversion('thumb')
            ->performOnCollections('image', 'banner')
            ->fit(Fit::Crop, 240, 240);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<CategoryPlacement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(CategoryPlacement::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product')->withPivot('sort_order');
    }

    /**
     * Products that name this category as their primary category.
     *
     * @return HasMany<Product, $this>
     */
    public function primaryProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'primary_category_id');
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /**
     * This category's id plus every descendant's, so a listing can filter on
     * `whereIn('category_id', ...)` without a recursive CTE.
     *
     * The whole (id, parent_id) edge list is fetched once and walked
     * breadth-first in memory: one query regardless of tree depth.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];

        foreach (static::query()->whereNotNull('parent_id')->pluck('parent_id', 'id') as $id => $parentId) {
            $childrenByParent[(int) $parentId][] = (int) $id;
        }

        $ids = [$this->id];
        $seen = [$this->id => true];
        $queue = [$this->id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Categories the storefront may render.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', CategoryStatus::Active);
    }

    /**
     * Top-level categories only.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }
}
