<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a placed {@see Order}, frozen at the moment of sale.
 *
 * The product and variant relations exist for reporting and for the "buy it
 * again" link, but nothing that renders this line reads them: the name, sku,
 * option label, price, discount share and tax are all columns, and
 * `product_snapshot` carries the rest. A line stays readable long after the
 * product it sold has been renamed, repriced or deleted.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $option_label
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property string $tax_rate
 * @property int $tax_cents
 * @property int $total_cents
 * @property array<string, mixed> $product_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 */
#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'name', 'sku', 'option_label',
    'quantity', 'unit_price_cents', 'subtotal_cents', 'discount_cents', 'tax_rate',
    'tax_cents', 'total_cents', 'product_snapshot',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
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
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'product_snapshot' => 'array',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
}
