<?php

namespace App\Http\Requests\Admin;

use App\Models\TaxClass;

/** Creating a tax class: no row for uniqueness to ignore. */
class TaxClassStoreRequest extends TaxClassRequest
{
    protected function taxClass(): ?TaxClass
    {
        return null;
    }
}
