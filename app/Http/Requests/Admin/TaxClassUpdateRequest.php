<?php

namespace App\Http\Requests\Admin;

use App\Models\TaxClass;

/** Editing a tax class: the bound row is what the slug check ignores. */
class TaxClassUpdateRequest extends TaxClassRequest
{
    protected function taxClass(): ?TaxClass
    {
        $taxClass = $this->route('taxClass');

        return $taxClass instanceof TaxClass ? $taxClass : null;
    }
}
