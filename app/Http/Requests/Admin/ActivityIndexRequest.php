<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The audit trail's filter bar, validated.
 *
 * `subject_type` is a class name and `sort` reaches `orderBy`, so both are
 * checked against closed lists rather than trusted. Everything else is optional:
 * an auditor arrives with a question, not a form.
 */
class ActivityIndexRequest extends FormRequest
{
    /**
     * Columns the trail may be ordered by.
     *
     * @var list<string>
     */
    public const SORTABLE = ['created_at', 'log_name', 'event'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'log_name' => ['nullable', 'string', 'max:60'],
            'event' => ['nullable', 'string', 'max:60'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'causer_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
