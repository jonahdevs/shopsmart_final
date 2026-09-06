<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The customers table's filter bar, validated.
 *
 * `sort` is checked against a closed set for the same reason the orders table
 * does it: it is interpolated into an `orderBy`, and a bound parameter cannot
 * protect an identifier. Two of the sortable names are query aggregates rather
 * than columns; both are aliased in the controller's own select, so the list
 * stays a set of literals either way.
 */
class CustomerIndexRequest extends FormRequest
{
    /**
     * Columns and aggregates the table may be sorted by.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'email', 'created_at', 'orders_count', 'lifetime_spent_cents'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
