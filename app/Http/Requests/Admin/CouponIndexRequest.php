<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The coupons table's filter bar, validated.
 *
 * `state` is a view over columns rather than a column itself — "live" folds
 * together the active flag and both ends of the date window, which is the
 * question staff actually ask of a code.
 */
class CouponIndexRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const SORTABLE = ['code', 'created_at', 'expires_at', 'used_count'];

    /**
     * @var list<string>
     */
    public const STATES = ['live', 'scheduled', 'expired', 'inactive'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(CouponType::class)],
            'state' => ['nullable', Rule::in(self::STATES)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
