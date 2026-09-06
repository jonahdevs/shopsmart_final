<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;

/**
 * Creating a product. Every uniqueness check has no row to ignore, which is the
 * only way this differs from {@see ProductUpdateRequest}.
 */
class ProductStoreRequest extends ProductRequest
{
    protected function product(): ?Product
    {
        return null;
    }
}
