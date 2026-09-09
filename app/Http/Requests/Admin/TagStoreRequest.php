<?php

namespace App\Http\Requests\Admin;

use Spatie\Tags\Tag;

/** Creating a tag: no row for uniqueness to ignore. */
class TagStoreRequest extends TagRequest
{
    protected function tag(): ?Tag
    {
        return null;
    }
}
