<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a persisted {@see Cart}: a product, optionally a specific
 * variant, a quantity, and the unit price the shopper was shown when the line
 * was opened.
 *
 * `unit_price_cents` is a snapshot, not a lookup. It is what lets the storefront
 * tell a shopper their price has moved, and it stops a catalog edit from
 * silently rewriting a cart underneath them. It is not an authority for money
 * taken — checkout re-prices against the catalog.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cart|null $cart
 * @property-read Product|null $product Null once the product is soft-deleted.
 * @property-read ProductVariant|null $variant
 */
#[Fillable(['cart_id', 'product_id', 'product_variant_id', 'quantity', 'unit_price_cents'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /** The line total in cents, at the captured unit price. */
    public function lineTotalCents(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }
}
