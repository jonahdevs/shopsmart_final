<?php

namespace App\Models;

use Database\Factories\CouponUseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One redemption of a {@see Coupon} against one {@see Order}.
 *
 * Written once, when payment confirms — never at placement, so an abandoned
 * checkout cannot eat a limited coupon's budget. The `(coupon_id, order_id)`
 * unique index is what makes a replayed payment confirmation harmless.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property int|null $user_id
 * @property int $discount_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Coupon|null $coupon
 * @property-read Order|null $order
 * @property-read User|null $user
 */
#[Fillable(['coupon_id', 'order_id', 'user_id', 'discount_cents'])]
class CouponUse extends Model
{
    /** @use HasFactory<CouponUseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_cents' => 'integer',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
