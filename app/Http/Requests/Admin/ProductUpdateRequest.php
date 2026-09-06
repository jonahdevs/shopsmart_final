<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;

/**
 * Editing a product. The bound row is what every uniqueness check ignores, so a
 * save that changes nothing does not report the product's own slug and SKU as
 * already taken.
 */
class ProductUpdateRequest extends ProductRequest
{
    protected function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
