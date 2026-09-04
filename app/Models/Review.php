<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A customer review of a product, held in a moderation queue until approved.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property string $author_name
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property ReviewStatus $status
 * @property bool $verified_purchase
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 * @property-read User|null $user
 */
#[Fillable(['product_id', 'user_id', 'author_name', 'rating', 'title', 'body', 'status', 'verified_purchase', 'approved_at'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'verified_purchase' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Moderation is an audit-worthy action, so only status transitions are logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('review');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Null once the reviewer deletes their account; the review survives,
     * attributed to the snapshotted author_name.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Publicly visible reviews. Product::withReviewStats() aggregates through
     * this scope, so it must filter on approved status and nothing else.
     *
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved);
    }

    /**
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ReviewStatus::Pending);
    }

    /**
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function forProduct(Builder $query, Product|int $product): void
    {
        $query->where('product_id', $product instanceof Product ? $product->getKey() : $product);
    }

    /**
     * Publish the review and stamp the moment it cleared moderation.
     */
    public function approve(): bool
    {
        return $this->forceFill([
            'status' => ReviewStatus::Approved,
            'approved_at' => now(),
        ])->save();
    }

    /**
     * Pull the review back out of public view, clearing the approval stamp so
     * approved_at always reflects the current status.
     */
    public function reject(): bool
    {
        return $this->forceFill([
            'status' => ReviewStatus::Rejected,
            'approved_at' => null,
        ])->save();
    }
}
