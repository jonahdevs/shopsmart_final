<?php

namespace App\Models;

use App\Enums\StockStatus;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One purchasable combination of a variable product's attribute values,
 * for example "Red / XL". Carries its own SKU, price and stock.
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $barcode
 * @property int|null $price
 * @property int|null $sale_price
 * @property int|null $cost_price
 * @property StockStatus $stock_status
 * @property int|null $stock_quantity
 * @property bool $allow_backorder
 * @property string|null $weight
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product|null $product Null when the parent product has been soft-deleted.
 * @property-read Collection<int, AttributeValue> $attributeValues
 */
#[Fillable([
    'product_id', 'sku', 'barcode', 'price', 'sale_price', 'cost_price',
    'stock_status', 'stock_quantity', 'allow_backorder', 'weight', 'length',
    'width', 'height', 'description', 'is_active', 'sort_order',
])]
class ProductVariant extends Model implements HasMedia
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'allow_backorder' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->performOnCollections('image')
            ->background('ffffff')
            ->fit(Fit::Crop, 600, 600);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsToMany<AttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant');
    }

    /** @param  Builder<ProductVariant>  $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<ProductVariant>  $query */
    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->where('stock_status', '!=', StockStatus::OutOfStock);
    }

    /**
     * The price a customer actually pays, in cents. Falls back to the parent
     * product's price when the variant does not override it.
     */
    public function effectivePriceCents(): ?int
    {
        return $this->sale_price
            ?? $this->price
            ?? $this->product?->effectivePriceCents();
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null
            && $this->price !== null
            && $this->sale_price < $this->price;
    }

    public function isInStock(): bool
    {
        return $this->stock_status !== StockStatus::OutOfStock;
    }

    /**
     * Human label for the combination, for example "Red / XL". Requires
     * `attributeValues` to be loaded, otherwise it costs a query per variant.
     */
    public function optionLabel(): string
    {
        return $this->attributeValues
            ->sortBy('sort_order')
            ->pluck('label')
            ->implode(' / ');
    }
}
