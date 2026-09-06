<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new discount code.
 *
 * Staff type whole KES; the database stores integer cents. The conversion
 * happens once, in {@see couponAttributes()}, through {@see Money::toMinor()} —
 * never as an inline `* 100`, which is how a rounding error becomes a price.
 *
 * `used_count` is absent from the payload on purpose and must stay absent. It
 * is maintained solely by {@see Order::recordCouponUse()}, which
 * increments it only when a `coupon_uses` row was actually created; an admin
 * form that could write it directly would let the counter and the redemption
 * rows disagree, and the counter is what enforces the usage limit.
 */
class StoreCouponRequest extends FormRequest
{
    /**
     * Ceiling on a money field, in whole KES.
     *
     * The columns are unsigned big integers, so the real limit is far higher;
     * this one exists so a typo with too many zeros is refused at the form
     * rather than becoming a coupon that gives the store away.
     */
    private const MAX_MAJOR = 10_000_000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($this->route('coupon')),
            ],
            'type' => ['required', Rule::enum(CouponType::class)],
            'amount' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === CouponType::Fixed->value),
                'nullable',
                'numeric',
                'min:0.01',
                'max:'.self::MAX_MAJOR,
            ],
            'percent' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === CouponType::Percent->value),
                'nullable',
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'min_subtotal' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_MAJOR],
            'max_discount' => ['nullable', 'numeric', 'min:0.01', 'max:'.self::MAX_MAJOR],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('A code may only use letters, numbers, hyphens and underscores.'),
            'percent.max' => __('A percentage discount cannot exceed 100%.'),
            'expires_at.after_or_equal' => __('The end of the window must not fall before its start.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_subtotal' => __('minimum spend'),
            'max_discount' => __('maximum discount'),
            'usage_limit_per_user' => __('per-customer limit'),
        ];
    }

    /**
     * Codes are stored uppercase — the storefront upcases what the shopper
     * types before looking one up, so a lowercase row would never match.
     * The checkbox is normalised here because an unticked one sends nothing at
     * all, and "absent" has to mean "off" rather than "leave as it was".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code'))
                ? mb_strtoupper(trim($this->input('code')))
                : $this->input('code'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * The validated form as coupon columns, with money converted to cents.
     *
     * The unit the customer does not pay in is nulled rather than left over:
     * switching a coupon from percentage to fixed must not leave a stale
     * `percent` behind for {@see Coupon::discountFor()} to find.
     * `max_discount_cents` only caps a percentage, so it goes the same way.
     *
     * @return array<string, mixed>
     */
    public function couponAttributes(): array
    {
        $money = app(Money::class);
        $type = CouponType::from((string) $this->validated('type'));
        $isPercent = $type === CouponType::Percent;

        $maxDiscount = $this->validated('max_discount');
        $minSubtotal = $this->validated('min_subtotal');

        return [
            'code' => $this->validated('code'),
            'type' => $type,
            'amount_cents' => $isPercent ? null : $money->toMinor((string) $this->validated('amount')),
            'percent' => $isPercent ? (float) $this->validated('percent') : null,
            'min_subtotal_cents' => $minSubtotal === null ? 0 : $money->toMinor((string) $minSubtotal),
            'max_discount_cents' => $isPercent && $maxDiscount !== null
                ? $money->toMinor((string) $maxDiscount)
                : null,
            'usage_limit' => $this->validated('usage_limit'),
            'usage_limit_per_user' => $this->validated('usage_limit_per_user'),
            'starts_at' => $this->validated('starts_at'),
            'expires_at' => $this->validated('expires_at'),
            'is_active' => (bool) $this->validated('is_active'),
            'description' => $this->validated('description'),
        ];
    }
}
