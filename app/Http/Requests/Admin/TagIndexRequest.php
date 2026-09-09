<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tags table's filter bar, validated. The sort column is checked against a
 * closed set because it is interpolated into an `orderBy` — `products_count` is
 * the alias of the correlated subquery the controller selects, not a column.
 */
class TagIndexRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const SORTABLE = ['name', 'products_count', 'created_at'];

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
