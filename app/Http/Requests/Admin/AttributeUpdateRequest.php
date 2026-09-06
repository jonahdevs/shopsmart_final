<?php

namespace App\Http\Requests\Admin;

use App\Models\Attribute;

/** Editing an attribute: the bound row is what the slug check ignores. */
class AttributeUpdateRequest extends AttributeRequest
{
    protected function attribute(): ?Attribute
    {
        $attribute = $this->route('attribute');

        return $attribute instanceof Attribute ? $attribute : null;
    }
}
