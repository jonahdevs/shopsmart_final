<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Joins a product to an attribute it uses.
 *
 * `is_variation_attribute` decides whether the attribute generates purchasable
 * variants (Size, Colour) or is display-only specification data (Material).
 *
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property array<int, string>|null $values
 * @property bool $is_variation_attribute
 * @property bool $is_visible
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Attribute $attribute
 */
#[Fillable(['product_id', 'attribute_id', 'values', 'is_variation_attribute', 'is_visible', 'sort_order'])]
class ProductAttribute extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_variation_attribute' => 'boolean',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Attributes shown on the product page's specification table.
     *
     * @param  Builder<ProductAttribute>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_visible', true)->orderBy('sort_order');
    }

    /**
     * Attributes that generate purchasable variants.
     *
     * @param  Builder<ProductAttribute>  $query
     */
    #[Scope]
    protected function variation(Builder $query): void
    {
        $query->where('is_variation_attribute', true)->orderBy('sort_order');
    }
}
