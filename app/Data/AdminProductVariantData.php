<?php

namespace App\Data;

use App\Models\ProductVariant;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One variant row in the product editor's variants repeater.
 *
 * Money arrives in major units here, unlike everywhere else in app/Data: this
 * object populates form inputs a staff member types whole KES into, and the
 * controller hands what they type back through {@see Money::toMinor()}. The
 * cents are never shown, so they are never sent.
 *
 * `price` is the struck-through original and `salePrice` what the customer
 * actually pays — the same convention as {@see AdminProductFormData}, and
 * deliberately not the inverted one the reference app used on variants only.
 */
#[TypeScript]
class AdminProductVariantData extends Data
{
    public function __construct(
        public int $id,
        public string $sku,
        public ?string $barcode,
        public ?float $price,
        public ?float $salePrice,
        public ?float $costPrice,
        public string $stockStatus,
        public ?int $stockQuantity,
        public bool $allowBackorder,
        public bool $isActive,
        public int $sortOrder,
        /** @var list<int> */
        public array $attributeValueIds,
    ) {}

    public static function fromModel(ProductVariant $variant): self
    {
        $money = app(Money::class);

        return new self(
            id: $variant->getKey(),
            sku: $variant->sku,
            barcode: $variant->barcode,
            price: $variant->price === null ? null : $money->toMajor($variant->price),
            salePrice: $variant->sale_price === null ? null : $money->toMajor($variant->sale_price),
            costPrice: $variant->cost_price === null ? null : $money->toMajor($variant->cost_price),
            stockStatus: $variant->stock_status->value,
            stockQuantity: $variant->stock_quantity,
            allowBackorder: $variant->allow_backorder,
            isActive: $variant->is_active,
            sortOrder: $variant->sort_order,
            attributeValueIds: array_values(
                $variant->attributeValues->map(fn ($value): int => (int) $value->getKey())->all(),
            ),
        );
    }
}
