<?php

namespace App\Models;

use App\Enums\SavedProductList;
use App\Support\StorefrontSession;
use Database\Factories\SavedProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One product a signed-in customer has saved, to their wishlist or to their
 * compare tray. `position` preserves the order they saved them in.
 *
 * The two lists share a table because they share a shape; they differ only in
 * how long they live and whether they are capped. As with the cart, the session
 * holds the live copy and {@see StorefrontSession} mirrors it here.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property SavedProductList $list
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Product|null $product
 */
#[Fillable(['user_id', 'product_id', 'list', 'position'])]
class SavedProduct extends Model
{
    /** @use HasFactory<SavedProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list' => SavedProductList::class,
            'position' => 'integer',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * One customer's entries in one list, in the order they saved them.
     *
     * The id tie-breaker keeps the order stable when two entries share a
     * position, which a merge can briefly produce.
     *
     * @param  Builder<SavedProduct>  $query
     */
    #[Scope]
    protected function forList(Builder $query, int $userId, SavedProductList $list): void
    {
        $query->where('user_id', $userId)
            ->where('list', $list)
            ->orderBy('position')
            ->orderBy('id');
    }
}
