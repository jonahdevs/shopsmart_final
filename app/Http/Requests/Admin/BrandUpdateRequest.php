<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;

/** Editing a brand: the bound row is what the slug check ignores. */
class BrandUpdateRequest extends BrandRequest
{
    protected function brand(): ?Brand
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand ? $brand : null;
    }
}
