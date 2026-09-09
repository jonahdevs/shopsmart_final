<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\Tags\Tag;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin tags table.
 *
 * `name` and `slug` are translatable JSON columns on the package's model, so
 * they are read through the model's accessor and land here as the plain strings
 * the table prints. The client never sees a locale map.
 *
 * `productCount` is a correlated subquery aggregate rather than a loaded
 * relation: it is the number staff read before deleting a tag, and a
 * merchandising tag can carry hundreds of products.
 */
#[TypeScript]
class AdminTagRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $productCount,
    ) {}

    public static function fromModel(Tag $tag): self
    {
        return new self(
            id: (int) $tag->getKey(),
            name: self::translated($tag, 'name'),
            slug: self::translated($tag, 'slug'),
            productCount: (int) ($tag->getAttribute('products_count') ?? 0),
        );
    }

    /** The current locale's value of a translatable column, as a string. */
    private static function translated(Tag $tag, string $attribute): string
    {
        $value = $tag->getAttribute($attribute);

        return is_string($value) ? $value : '';
    }
}
