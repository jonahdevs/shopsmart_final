<?php

namespace App\Models;

use App\Enums\ProductLinkType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A curated, directed recommendation from one product to another.
 *
 * One typed table covers upsells, cross-sells, accessories and spare parts:
 * they share the same shape and differ only by `type`. `is_required` and
 * `default_quantity` are accessory-only — they drive the "complete your
 * purchase" prompt, where a required accessory is pre-checked and its stepper
 * starts at the quantity that product actually needs.
 *
 * @property int $id
 * @property int $product_id
 * @property int $linked_product_id
 * @property ProductLinkType $type
 * @property bool $is_required
 * @property int $default_quantity
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Product $linkedProduct
 */
#[Fillable(['product_id', 'linked_product_id', 'type', 'is_required', 'default_quantity', 'sort_order'])]
class ProductLink extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductLinkType::class,
            'is_required' => 'boolean',
            'default_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function linkedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'linked_product_id');
    }

    /** @param  Builder<ProductLink>  $query */
    #[Scope]
    protected function ofType(Builder $query, ProductLinkType $type): void
    {
        $query->where('type', $type);
    }
}
