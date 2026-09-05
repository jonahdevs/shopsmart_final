<?php

namespace App\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules and the model resolution shared by every request that names a
 * product — add to cart, change a line, save to a list.
 *
 * Resolution lives here rather than in the controllers because the cross-field
 * check ("is this actually buyable?") needs the models during validation, and
 * making the controller load them a second time is how the two readings drift
 * apart. The resolved models are memoised, so the request and the controller
 * share one query.
 */
trait StorefrontProductValidationRules
{
    private ?Product $resolvedProduct = null;

    private bool $productResolved = false;

    private ?ProductVariant $resolvedVariant = null;

    private bool $variantResolved = false;

    /**
     * A soft-deleted product is gone as far as the storefront is concerned, so
     * it must not satisfy `exists` — the default rule would accept it.
     *
     * @return list<ValidationRule|string>
     */
    protected function productIdRules(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('products', 'id')->whereNull('deleted_at'),
        ];
    }

    /**
     * @return list<ValidationRule|string>
     */
    protected function variantIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('product_variants', 'id')->whereNull('deleted_at'),
        ];
    }

    /**
     * @return list<string>
     */
    protected function quantityRules(int $min = 1): array
    {
        return ['nullable', 'integer', 'min:'.$min, 'max:999'];
    }

    /**
     * The product named by the request, with everything a cart line renders
     * already loaded. Null when the id matches nothing on the storefront.
     */
    public function product(): ?Product
    {
        if ($this->productResolved) {
            return $this->resolvedProduct;
        }

        $this->productResolved = true;

        $this->resolvedProduct = Product::query()
            ->with(['brand:id,name,slug', 'media'])
            ->whereKey($this->integer('product_id'))
            ->first();

        return $this->resolvedProduct;
    }

    /**
     * The chosen variant, scoped to the named product so a variant id from a
     * different product cannot be smuggled onto this line.
     */
    public function variant(): ?ProductVariant
    {
        if ($this->variantResolved) {
            return $this->resolvedVariant;
        }

        $this->variantResolved = true;

        $product = $this->product();
        $variantId = $this->input('variant_id');

        if ($product === null || $variantId === null) {
            return $this->resolvedVariant = null;
        }

        $this->resolvedVariant = $product->variants()
            ->with(['media', 'attributeValues'])
            ->whereKey((int) $variantId)
            ->first();

        return $this->resolvedVariant;
    }

    /** Whether the request asked for a specific variant, whether or not it resolved. */
    protected function wantsVariant(): bool
    {
        return $this->input('variant_id') !== null;
    }
}
