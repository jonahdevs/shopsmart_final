<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;

/**
 * Creating a category. A category that does not exist yet has no subtree, so
 * the cycle guard has nothing to refuse — any existing parent is legal.
 */
class CategoryStoreRequest extends CategoryRequest
{
    protected function category(): ?Category
    {
        return null;
    }
}
