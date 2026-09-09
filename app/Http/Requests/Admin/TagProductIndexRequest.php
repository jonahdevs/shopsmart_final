<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The one tag's products screen, validated.
 *
 * Two search terms, because the screen asks two questions at once: `search`
 * narrows the products already carrying the tag, `add` looks for ones that do
 * not yet. Keeping them apart is what lets a staff member hunt for something to
 * add without losing the list they were reading.
 */
class TagProductIndexRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const SORTABLE = ['name', 'created_at'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'add' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
