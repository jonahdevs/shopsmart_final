<?php

namespace App\Http\Requests\Admin;

use Spatie\Tags\Tag;

/** Editing a tag: the bound row is what the name check ignores. */
class TagUpdateRequest extends TagRequest
{
    protected function tag(): ?Tag
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag ? $tag : null;
    }
}
