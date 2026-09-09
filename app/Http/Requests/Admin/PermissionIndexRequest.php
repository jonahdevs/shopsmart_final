<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The permissions table's filter bar, validated.
 *
 * `sort` reaches `orderBy`, so it is checked against a closed list rather than
 * trusted. `group` is matched with a `like` prefix, so it is bounded in length
 * and character set — a group is a permission's first segment and those are
 * lowercase words in the seeder.
 */
class PermissionIndexRequest extends FormRequest
{
    /**
     * Columns the table may be ordered by.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:60'],
            'group' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_]+$/'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
