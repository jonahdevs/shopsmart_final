<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * The staff table's filter bar, validated.
 *
 * `sort` is the only place a query string on this page would otherwise reach
 * `orderBy`, so it is checked against a closed list rather than escaped.
 */
class StaffIndexRequest extends FormRequest
{
    /**
     * Columns the table may be sorted by.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'email', 'created_at'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', Rule::exists(Role::class, 'name')],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
