<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;

/** Creating a brand: no row for uniqueness to ignore. */
class BrandStoreRequest extends BrandRequest
{
    protected function brand(): ?Brand
    {
        return null;
    }
}
