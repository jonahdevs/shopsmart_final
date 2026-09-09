<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\Tags\Tag;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A tag as its editor holds it. The create page renders the same component from
 * {@see self::blank()}, so `id` is null until the row exists.
 *
 * There is no slug field. The package generates the slug from the name in a
 * `saving` hook and nothing in this application looks a tag up by it — the
 * storefront rails match on the name — so offering staff a slug to edit would
 * be offering them a value with no consequence.
 */
#[TypeScript]
class AdminTagFormData extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $slug,
    ) {}

    public static function blank(): self
    {
        return new self(id: null, name: '', slug: null);
    }

    public static function fromModel(Tag $tag): self
    {
        $name = $tag->getAttribute('name');
        $slug = $tag->getAttribute('slug');

        return new self(
            id: (int) $tag->getKey(),
            name: is_string($name) ? $name : '',
            slug: is_string($slug) ? $slug : null,
        );
    }
}
