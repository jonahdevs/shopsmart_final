<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The orders table's filter bar, validated.
 *
 * Every filter is optional and every one is validated against a closed set —
 * the sort column especially, which is interpolated into an `orderBy` and would
 * otherwise be the one place on this page a query string reaches SQL.
 */
class OrderIndexRequest extends FormRequest
{
    /**
     * Columns the table may be sorted by. Anything else is rejected before it
     * can reach `orderBy`.
     *
     * @var list<string>
     */
    public const SORTABLE = ['placed_at', 'total_cents', 'order_number', 'status'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
