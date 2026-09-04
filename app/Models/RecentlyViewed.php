<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per authenticated user per product, refreshed on each view. Backs the
 * account area's "recently viewed" rail.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property Carbon $viewed_at
 * @property-read User|null $user
 * @property-read Product|null $product
 */
#[Fillable(['user_id', 'product_id', 'viewed_at'])]
class RecentlyViewed extends Model
{
    protected $table = 'recently_viewed';

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Insert or refresh the user's row for this product in a single statement,
     * leaning on the [user_id, product_id] unique key so concurrent views can
     * never race a read-then-write into duplicate rows.
     */
    public static function record(User $user, Product $product): void
    {
        static::upsert(
            [['user_id' => $user->getKey(), 'product_id' => $product->getKey(), 'viewed_at' => now()]],
            uniqueBy: ['user_id', 'product_id'],
            update: ['viewed_at'],
        );
    }
}
