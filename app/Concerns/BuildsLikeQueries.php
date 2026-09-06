<?php

namespace App\Concerns;

use App\Http\Controllers\Shop\Concerns\FiltersCatalogProducts;

/**
 * The one way this application writes a LIKE.
 *
 * The escape character is deliberately not a backslash. MySQL treats `\` as
 * LIKE's default escape while SQLite has none, so a backslash-escaped wildcard
 * matches itself on one driver and nothing at all on the other — and the suite
 * runs on SQLite while production runs on MySQL. Naming the character
 * explicitly makes both dialects agree.
 *
 * {@see FiltersCatalogProducts} carries its own copy of this rule for the
 * storefront's catalog filters. The two must not disagree; they are separate
 * only because that trait is a catalog-specific filter builder and the admin
 * tables need the escaping without the filtering. Fold them together once both
 * are on one branch.
 */
trait BuildsLikeQueries
{
    private const LIKE_ESCAPE = '!';

    /**
     * A `column LIKE ? ESCAPE '!'` fragment.
     *
     * The column is constrained to a literal from a closed set rather than an
     * arbitrary string, so the result is a `literal-string` and PHPStan's guard
     * against assembling SQL out of runtime values still holds. The pattern
     * itself is bound.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    protected function likeExpression(string $column): string
    {
        return $column." LIKE ? ESCAPE '".self::LIKE_ESCAPE."'";
    }

    /**
     * Wrap a staff member's term as a LIKE "contains" pattern.
     *
     * The term is bound, so the wildcards were never an injection risk — they
     * were a correctness one: a bare `%` matched every row and `a_b` matched
     * "axb". Escaped, a typed `%` or `_` matches itself.
     */
    protected function containsPattern(string $term): string
    {
        $escape = self::LIKE_ESCAPE;

        // The escape character itself has to be escaped first, or escaping the
        // wildcards would produce sequences this pass then re-escapes.
        $escaped = str_replace(
            [$escape, '%', '_'],
            [$escape.$escape, $escape.'%', $escape.'_'],
            $term,
        );

        return '%'.$escaped.'%';
    }
}
