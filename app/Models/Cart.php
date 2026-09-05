<?php

namespace App\Models;

use App\Support\StorefrontSession;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The durable copy of one customer's cart, one row per user.
 *
 * The live cart is the session copy — {@see StorefrontSession} is
 * the only thing that writes here, mirroring the session on every mutation so
 * the cart survives a new session, a second device and, later, an
 * abandoned-cart reminder.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, CartItem> $items
 */
#[Fillable(['user_id', 'last_activity_at'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
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

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /**
     * Stamp the cart as freshly touched, for idle/abandoned detection.
     *
     * forceFill rather than update() because `last_activity_at` is bookkeeping,
     * not something a request body should ever be able to set.
     */
    public function markActive(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Subtotal of the persisted lines in cents, at the prices captured when
     * each line was opened. Requires `items` to be loaded.
     */
    public function subtotalCents(): int
    {
        return (int) $this->items->sum(
            fn (CartItem $item): int => $item->unit_price_cents * $item->quantity,
        );
    }
}
