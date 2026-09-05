<?php

namespace App\Models;

use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A discount code the shopper can apply to their cart.
 *
 * `amount_cents` and `percent` are separate columns rather than one overloaded
 * value, so no caller has to consult `type` to know what unit it is holding.
 * Exactly one is set.
 *
 * This model owns both halves of the rules: {@see validateFor()} decides whether
 * a coupon may be used and says why not in the shopper's language, and
 * {@see discountFor()} decides what it takes off. Every caller — applying it in
 * the cart, re-checking it at placement, admin previews — goes through these two
 * methods, so there is one definition of "valid" in the application.
 *
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int|null $amount_cents
 * @property string|null $percent
 * @property int $min_subtotal_cents
 * @property int|null $max_discount_cents
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property int $used_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CouponUse> $uses
 */
#[Fillable([
    'code', 'type', 'amount_cents', 'percent', 'min_subtotal_cents', 'max_discount_cents',
    'usage_limit', 'usage_limit_per_user', 'used_count', 'starts_at', 'expires_at',
    'is_active', 'description',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'amount_cents' => 'integer',
            'min_subtotal_cents' => 'integer',
            'max_discount_cents' => 'integer',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return HasMany<CouponUse, $this> */
    public function uses(): HasMany
    {
        return $this->hasMany(CouponUse::class);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Coupons that are switched on and inside their date window.
     *
     * @param  Builder<Coupon>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $started) => $started
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $unexpired) => $unexpired
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()));
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /**
     * Why this coupon cannot be used, or null when it can.
     *
     * Returns a message rather than a boolean because every rejection has a
     * different remedy — "spend a bit more" and "this expired" should not read
     * the same to a shopper. Checks run cheapest-first, and the two that need a
     * query come last.
     */
    public function validateFor(?User $user, int $subtotalCents): ?string
    {
        if (! $this->is_active) {
            return __('This code is no longer available.');
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return __('This code is not active yet.');
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return __('This code has expired.');
        }

        if ($subtotalCents < $this->min_subtotal_cents) {
            return __('This code needs a subtotal of at least :amount.', [
                'amount' => money($this->min_subtotal_cents),
            ]);
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return __('This code has been fully redeemed.');
        }

        if ($user !== null && $this->usage_limit_per_user !== null) {
            $used = $this->uses()->where('user_id', $user->getKey())->count();

            if ($used >= $this->usage_limit_per_user) {
                return __('You have already used this code.');
            }
        }

        return null;
    }

    /**
     * What this coupon takes off the given subtotal, in cents.
     *
     * A fixed discount can never exceed the subtotal, and a percentage one is
     * additionally capped by `max_discount_cents` when set — without that cap a
     * percentage coupon is an unbounded liability on a large order.
     */
    public function discountFor(int $subtotalCents): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }

        $discount = match ($this->type) {
            CouponType::Fixed => (int) ($this->amount_cents ?? 0),
            CouponType::Percent => (int) round($subtotalCents * ((float) $this->percent) / 100),
        };

        if ($this->max_discount_cents !== null) {
            $discount = min($discount, $this->max_discount_cents);
        }

        return max(0, min($discount, $subtotalCents));
    }
}
