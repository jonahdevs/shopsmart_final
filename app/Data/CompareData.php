<?php

namespace App\Data;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The compare tray: the products being compared, plus their visible
 * specification attributes rolled into aligned rows.
 *
 * Price, rating and availability are not rows here — they live on each
 * {@see ProductCardData} and the table renders them from there.
 */
#[TypeScript]
class CompareData extends Data
{
    /**
     * @param  list<ProductCardData>  $products
     * @param  list<CompareAttributeData>  $attributes
     */
    public function __construct(
        public array $products,
        public array $attributes,
        public int $limit,
    ) {}

    /**
     * @param  Collection<int, Product>  $products  With `productAttributes.attribute` loaded, in tray order.
     */
    public static function fromProducts(Collection $products, int $limit): self
    {
        return new self(
            products: array_values($products
                ->map(fn (Product $product): ProductCardData => ProductCardData::fromModel($product))
                ->all()),
            attributes: self::attributeRows($products),
            limit: $limit,
        );
    }

    /**
     * Build one row per attribute name, in the order the attributes are first
     * met walking the tray left to right, with a value slot per product.
     *
     * @param  Collection<int, Product>  $products
     * @return list<CompareAttributeData>
     */
    private static function attributeRows(Collection $products): array
    {
        /** @var array<string, array<int, string>> $rows */
        $rows = [];

        foreach ($products->values() as $index => $product) {
            foreach ($product->productAttributes as $productAttribute) {
                $value = self::attributeValue($productAttribute);

                if ($value === null) {
                    continue;
                }

                $name = $productAttribute->attribute->name;

                $rows[$name] ??= [];
                $rows[$name][$index] = $value;
            }
        }

        $count = $products->count();

        return array_map(
            fn (string $name, array $values): CompareAttributeData => new CompareAttributeData(
                name: $name,
                values: array_map(
                    fn (int $index): ?string => $values[$index] ?? null,
                    range(0, max(0, $count - 1)),
                ),
            ),
            array_keys($rows),
            $rows,
        );
    }

    /**
     * A product attribute holds a list of values ("Steel, Aluminium"); the
     * compare table shows them as one comma-joined cell.
     */
    private static function attributeValue(ProductAttribute $productAttribute): ?string
    {
        $values = array_values(array_filter(
            array_map('trim', array_map('strval', $productAttribute->values ?? [])),
            fn (string $value): bool => $value !== '',
        ));

        return $values === [] ? null : implode(', ', $values);
    }
}
