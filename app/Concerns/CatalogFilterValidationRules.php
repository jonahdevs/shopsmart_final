<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The query-string contract shared by the catalog and category listings.
 *
 * Both pages read the same filters, so the rules live in one place; the
 * category page simply drops the ones it does not offer.
 */
trait CatalogFilterValidationRules
{
    /**
     * Get the validation rules used to validate a faceted listing's filters.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function catalogFilterRules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'cat' => ['sometimes', 'array', 'max:50'],
            'cat.*' => ['string', 'max:255'],
            'brand' => ['sometimes', 'array', 'max:50'],
            'brand.*' => ['integer', 'min:1'],
            'pmin' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'pmax' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'stock' => ['sometimes', 'boolean'],
            'rating' => ['sometimes', 'integer', 'between:0,5'],
            'tag' => ['sometimes', 'string', 'max:255'],
            'arrivals' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', 'in:popularity,newest,name-asc,price-asc,price-desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the validation rules used to validate a search term.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function searchTermRules(int $minimumLength = 2): array
    {
        return ['required', 'string', "min:{$minimumLength}", 'max:255'];
    }
}
