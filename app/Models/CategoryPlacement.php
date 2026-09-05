<?php

namespace App\Models;

use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Observers\CategoryPlacementObserver;
use Database\Factories\CategoryPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pins a category into one storefront location (navbar, home page, footer) at
 * a given position. A category may appear in each location at most once.
 *
 * @property int $id
 * @property int $category_id
 * @property CategorySection $location
 * @property int $sort_order
 * @property CategoryStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 */
#[Fillable(['category_id', 'location', 'sort_order', 'status'])]
#[ObservedBy(CategoryPlacementObserver::class)]
class CategoryPlacement extends Model
{
    /** @use HasFactory<CategoryPlacementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => CategorySection::class,
            'status' => CategoryStatus::class,
            'sort_order' => 'integer',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Live placements for one storefront location, in display order.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forLocation(Builder $query, CategorySection $location): void
    {
        $query->where('location', $location)->orderBy('sort_order');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        // The placement's own status is not enough: CategoryController::show()
        // 404s on anything but an active category, so a live placement pointing
        // at a draft one puts a dead link in the nav and on the home page.
        $query->where('status', CategoryStatus::Active)
            ->whereHas('category', $this->constrainToActiveCategory(...));
    }

    /**
     * @param  Builder<Category>  $query
     */
    private function constrainToActiveCategory(Builder $query): void
    {
        $query->active();
    }
}
