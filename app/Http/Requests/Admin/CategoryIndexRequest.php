<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategoryStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The categories table's filter bar.
 *
 * There is no sort here on purpose: the table is a tree rendered depth-first
 * from the roots, and a sortable column would flatten the one thing the page
 * exists to show. Ordering within a level is the category's own `sort_order`.
 */
class CategoryIndexRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(CategoryStatus::class)],
        ];
    }
}
