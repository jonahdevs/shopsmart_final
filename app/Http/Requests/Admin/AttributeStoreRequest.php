<?php

namespace App\Http\Requests\Admin;

use App\Models\Attribute;

/** Creating an attribute: no row for uniqueness to ignore. */
class AttributeStoreRequest extends AttributeRequest
{
    protected function attribute(): ?Attribute
    {
        return null;
    }
}
