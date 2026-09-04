<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Append-only analytics log of product views, covering guests as well as
 * signed-in users. Feeds the "customers also viewed" rail.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property Carbon $viewed_at
 * @property-read Product|null $product
 * @property-read User|null $user
 */
#[Fillable(['product_id', 'user_id', 'session_id', 'viewed_at'])]
class ProductView extends Model
{
    /**
     * How long a session's view of a given product suppresses further rows.
     */
    private const THROTTLE_MINUTES = 30;

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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Null for guest views.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a view for analytics, throttled to once per 30 minutes per
     * session+product so refreshes and back-navigation don't inflate the count.
     * Cache::add() is atomic, so two concurrent requests can't both win.
     *
     * A view with no session is not recorded at all. It cannot be throttled —
     * every sessionless request would share one key per product, so a single
     * such view would suppress all the others store-wide for half an hour — and
     * it cannot be attributed either, since "customers also viewed" is built by
     * joining product_views to itself on session_id.
     */
    public static function record(Product $product, ?User $user, ?string $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }

        $throttleKey = "product-view:{$sessionId}:{$product->getKey()}";

        if (! Cache::add($throttleKey, true, now()->addMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        static::create([
            'product_id' => $product->getKey(),
            'user_id' => $user?->getKey(),
            'session_id' => $sessionId,
            'viewed_at' => now(),
        ]);
    }
}
