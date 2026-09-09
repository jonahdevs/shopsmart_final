<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Tags\Tag;

/**
 * Everything the tag editor may send, validated once for both create and edit.
 * The two differ only in which row uniqueness ignores.
 *
 * A tag carries no `type`, deliberately. The package supports one, and the
 * reference build offers it as a free-text field — but every place this
 * application resolves a tag does it by name alone (the home page's Featured
 * rail, the New Arrival badge, the catalog's `?tag=` filter). A second tag
 * named "Featured" under some other type would therefore join the same rail
 * without appearing to be the same tag, which is a trap for whoever created it.
 * Until something reads `type`, offering it would be offering a footgun.
 *
 * Because there is only ever the untyped set, the name is unique across the
 * whole table rather than per type. The column is translatable JSON, so
 * uniqueness is checked against the current locale's path inside it.
 */
abstract class TagRequest extends FormRequest
{
    /** The tag being edited, or null when one is being created. */
    abstract protected function tag(): ?Tag;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'name->'.Tag::getLocale())->ignore($this->tag()?->getKey()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('A tag with this name already exists.'),
        ];
    }

    public function tagName(): string
    {
        return trim((string) $this->validated('name'));
    }
}
