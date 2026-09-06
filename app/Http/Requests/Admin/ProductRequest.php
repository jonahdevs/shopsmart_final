<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductLinkType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Everything the product editor may send, validated once for both create and
 * edit.
 *
 * The two differ only in which row a uniqueness check must ignore, so they are
 * two thin subclasses over one rule set rather than two copies of eighty rules
 * that can drift apart.
 *
 * **Money arrives in major units.** Staff type whole KES into the form; the
 * catalog columns (`price`, `sale_price`, `cost_price`, unsuffixed but integer
 * cents all the same) are written from {@see Money::toMinor()} in
 * {@see self::productAttributes()}. Nothing here multiplies by 100.
 */
abstract class ProductRequest extends FormRequest
{
    /** The product being edited, or null when one is being created. */
    abstract protected function product(): ?Product;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->product()?->getKey();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            // Left blank the controller slugs the name. Supplied, it must still
            // be a slug: the storefront binds a product by this column.
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            // A variable product carries no SKU of its own — its variants do.
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($productId)],
            'model_number' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            // Scheduled means "publish at a time", so the time is not optional.
            'published_at' => ['nullable', 'date', Rule::requiredIf(fn (): bool => $this->input('status') === ProductStatus::Scheduled->value)],
            'visibility' => ['required', Rule::enum(ProductVisibility::class)],

            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'primary_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'distinct', 'exists:categories,id'],

            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:65000'],
            'technical_specification' => ['nullable', 'string', 'max:65000'],

            // Whole KES, not cents. A null price is price-on-application.
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'lte:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],

            'is_taxable' => ['required', 'boolean'],
            'tax_class_id' => ['nullable', 'integer', 'exists:tax_classes,id'],
            'is_virtual' => ['required', 'boolean'],
            'requires_shipping' => ['required', 'boolean'],

            'stock_status' => ['required', Rule::enum(StockStatus::class)],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'allow_backorder' => ['required', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'tags' => ['nullable', 'string', 'max:500'],

            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            // Uniqueness is checked in withValidator(): every row must ignore a
            // different id, which a wildcard rule cannot express.
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'lte:variants.*.price'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'variants.*.stock_status' => ['required', Rule::enum(StockStatus::class)],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.allow_backorder' => ['required', 'boolean'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.attribute_value_ids' => ['nullable', 'array'],
            'variants.*.attribute_value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],

            'links' => ['nullable', 'array', 'max:100'],
            'links.*.type' => ['required', Rule::enum(ProductLinkType::class)],
            'links.*.linked_product_id' => ['required', 'integer', 'exists:products,id'],
            'links.*.is_required' => ['required', 'boolean'],
            'links.*.default_quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'links.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];

        return $rules;
    }

    /**
     * The checks that need to see the whole payload at once.
     *
     * A variant SKU is unique across the entire `product_variants` table and
     * every row has to ignore a different id, which no wildcard rule can
     * express; a self-link and a duplicate link are both facts about the set
     * rather than about one field. Doing these here keeps them reportable
     * against the exact row that is wrong.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertVariantSkusAreFree($validator);
            $this->assertLinksAreSane($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_price.lte' => __('The sale price cannot be higher than the price it is discounted from.'),
            'variants.*.sale_price.lte' => __('A variant sale price cannot be higher than the price it is discounted from.'),
        ];
    }

    /**
     * No two rows may claim the same SKU, and none may claim one another
     * variant already holds.
     *
     * Soft-deleted variants are included: they keep their SKU and the unique
     * index does not exclude them, so a reused one would fail at the database
     * rather than in validation.
     */
    private function assertVariantSkusAreFree(Validator $validator): void
    {
        $seen = [];

        foreach ($this->rawVariantRows() as $index => $row) {
            $sku = $row['sku'] ?? null;

            if (! is_string($sku) || trim($sku) === '') {
                continue;
            }

            $sku = trim($sku);

            if (isset($seen[$sku])) {
                $validator->errors()->add("variants.$index.sku", __('Two variants cannot share a SKU.'));

                continue;
            }

            $seen[$sku] = true;

            $variantId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;

            $taken = ProductVariant::withTrashed()
                ->where('sku', $sku)
                ->whereKeyNot($variantId)
                ->exists();

            if ($taken) {
                $validator->errors()->add("variants.$index.sku", __('That SKU already belongs to another variant.'));
            }
        }
    }

    /**
     * A product may not recommend itself, and may not recommend the same
     * product twice under one link type — `product_links` has a unique index on
     * exactly that triple, so a duplicate would fail at the database.
     */
    private function assertLinksAreSane(Validator $validator): void
    {
        $productId = $this->product()?->getKey();
        $seen = [];

        foreach ((array) $this->input('links', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $linkedId = is_numeric($row['linked_product_id'] ?? null) ? (int) $row['linked_product_id'] : null;
            $type = is_string($row['type'] ?? null) ? $row['type'] : '';

            if ($linkedId === null) {
                continue;
            }

            if ($productId !== null && $linkedId === $productId) {
                $validator->errors()->add("links.$index.linked_product_id", __('A product cannot be linked to itself.'));

                continue;
            }

            $key = $type.':'.$linkedId;

            if (isset($seen[$key])) {
                $validator->errors()->add("links.$index.linked_product_id", __('That product is already linked under this type.'));

                continue;
            }

            $seen[$key] = true;
        }
    }

    /**
     * The product's own columns, ready for a mass assignment.
     *
     * This is where major units become cents and nowhere else. The slug is
     * derived from the name when the staff member left it blank, so a product
     * always has one — the storefront binds on it.
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $slug = $this->nullableString('slug');

        return [
            'name' => (string) $this->validated('name'),
            'slug' => $slug ?? $this->uniqueSlugFromName(),
            'sku' => $this->nullableString('sku'),
            'model_number' => $this->nullableString('model_number'),
            'type' => (string) $this->validated('type'),
            'status' => (string) $this->validated('status'),
            'published_at' => $this->nullableString('published_at'),
            'visibility' => (string) $this->validated('visibility'),
            'brand_id' => $this->nullableInt('brand_id'),
            'primary_category_id' => $this->nullableInt('primary_category_id'),
            'short_description' => $this->nullableString('short_description'),
            'description' => $this->nullableString('description'),
            'technical_specification' => $this->nullableString('technical_specification'),
            'price' => $this->nullableCents('price'),
            'sale_price' => $this->nullableCents('sale_price'),
            'cost_price' => $this->nullableCents('cost_price'),
            'is_taxable' => $this->validatedFlag('is_taxable'),
            'tax_class_id' => $this->nullableInt('tax_class_id'),
            'is_virtual' => $this->validatedFlag('is_virtual'),
            'requires_shipping' => $this->validatedFlag('requires_shipping'),
            'stock_status' => (string) $this->validated('stock_status'),
            'stock_quantity' => $this->nullableInt('stock_quantity'),
            'allow_backorder' => $this->validatedFlag('allow_backorder'),
            'low_stock_threshold' => $this->nullableInt('low_stock_threshold'),
            'min_order_quantity' => $this->nullableInt('min_order_quantity'),
            'meta_title' => $this->nullableString('meta_title'),
            'meta_description' => $this->nullableString('meta_description'),
            'canonical_url' => $this->nullableString('canonical_url'),
            'sort_order' => $this->nullableInt('sort_order') ?? 0,
        ];
    }

    /**
     * The categories the product is filed in, on top of its primary one.
     *
     * The primary category is folded in because catalog membership is
     * `primary_category_id` OR the pivot and the storefront reads both — a
     * product whose primary category was not also pivoted would drop out of
     * half the listings that should hold it.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        $ids = [];

        foreach ((array) $this->validated('categories', []) as $id) {
            if (is_numeric($id)) {
                $ids[(int) $id] = true;
            }
        }

        $primary = $this->nullableInt('primary_category_id');

        if ($primary !== null) {
            $ids[$primary] = true;
        }

        return array_map(intval(...), array_keys($ids));
    }

    /**
     * The tag names typed into the single comma-separated field.
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        $raw = $this->nullableString('tags');

        if ($raw === null) {
            return [];
        }

        $names = [];

        foreach (explode(',', $raw) as $name) {
            $name = trim($name);

            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return array_map(strval(...), array_keys($names));
    }

    /**
     * The variant rows, with money already converted to cents.
     *
     * @return list<array<string, mixed>>
     */
    public function variantRows(): array
    {
        $money = app(Money::class);
        $rows = [];

        foreach ($this->rawVariantRows() as $index => $row) {
            $rows[] = [
                'id' => $this->nullableIntValue($row['id'] ?? null),
                'sku' => (string) ($row['sku'] ?? ''),
                'barcode' => $this->nullableStringValue($row['barcode'] ?? null),
                'price' => $this->centsValue($money, $row['price'] ?? null),
                'sale_price' => $this->centsValue($money, $row['sale_price'] ?? null),
                'cost_price' => $this->centsValue($money, $row['cost_price'] ?? null),
                'stock_status' => (string) ($row['stock_status'] ?? StockStatus::InStock->value),
                'stock_quantity' => $this->nullableIntValue($row['stock_quantity'] ?? null),
                'allow_backorder' => (bool) ($row['allow_backorder'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'sort_order' => $this->nullableIntValue($row['sort_order'] ?? null) ?? $index,
                'attribute_value_ids' => array_values(array_map(
                    intval(...),
                    array_filter((array) ($row['attribute_value_ids'] ?? []), is_numeric(...)),
                )),
            ];
        }

        return $rows;
    }

    /**
     * The typed link rows.
     *
     * @return list<array<string, mixed>>
     */
    public function linkRows(): array
    {
        $rows = [];

        foreach ((array) $this->input('links', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'type' => (string) ($row['type'] ?? ProductLinkType::Upsell->value),
                'linked_product_id' => (int) ($row['linked_product_id'] ?? 0),
                'is_required' => (bool) ($row['is_required'] ?? false),
                'default_quantity' => $this->nullableIntValue($row['default_quantity'] ?? null) ?? 1,
                'sort_order' => $this->nullableIntValue($row['sort_order'] ?? null) ?? (is_int($index) ? $index : 0),
            ];
        }

        return $rows;
    }

    /**
     * The raw `variants` input, keyed by index. Read from the input rather than
     * the validated set because the after-validation checks report against the
     * index the browser sent, and a rejected row still has to be named.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function rawVariantRows(): array
    {
        $rows = [];

        foreach ((array) $this->input('variants', []) as $index => $row) {
            if (is_array($row)) {
                $rows[$index] = $row;
            }
        }

        return $rows;
    }

    /**
     * A slug the products table will accept, derived from the name.
     *
     * Suffixed on collision rather than rejected: a staff member who left the
     * slug blank asked for one to be chosen, and "Blue Widget" being the second
     * product of that name is not an error worth stopping them for.
     */
    private function uniqueSlugFromName(): string
    {
        $base = Str::slug((string) $this->validated('name'));
        $base = $base === '' ? 'product' : $base;
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()
            ->where('slug', $slug)
            ->whereKeyNot($this->product()?->getKey() ?? 0)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function nullableInt(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /** A major-unit form field as the integer cents the column stores. */
    private function nullableCents(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? app(Money::class)->toMinor((float) $value) : null;
    }

    private function validatedFlag(string $key): bool
    {
        return (bool) $this->validated($key);
    }

    private function nullableStringValue(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function nullableIntValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function centsValue(Money $money, mixed $value): ?int
    {
        return is_numeric($value) ? $money->toMinor((float) $value) : null;
    }
}
