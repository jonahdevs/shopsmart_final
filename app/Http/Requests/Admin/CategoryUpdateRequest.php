<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;

/**
 * Editing a category. The bound row is what the slug uniqueness check ignores
 * and what the cycle guard measures the chosen parent against.
 */
class CategoryUpdateRequest extends CategoryRequest
{
    protected function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }
}
