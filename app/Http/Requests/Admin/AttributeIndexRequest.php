<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttributeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The attributes table's filter bar, validated. The sort column is checked
 * against a closed set because it is interpolated into an `orderBy`.
 */
class AttributeIndexRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const SORTABLE = ['name', 'sort_order', 'created_at'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(AttributeType::class)],
            'active' => ['nullable', Rule::in(['1', '0'])],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
