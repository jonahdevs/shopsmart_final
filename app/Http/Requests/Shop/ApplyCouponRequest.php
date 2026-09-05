<?php

namespace App\Http\Requests\Shop;

use App\Concerns\CheckoutValidationRules;
use App\Models\Coupon;
use App\Support\CheckoutPricer;
use App\Support\StorefrontSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A discount code the shopper is trying to use.
 *
 * The rejection message comes from {@see Coupon::validateFor()} so
 * there is one definition of "valid" — the wording a shopper sees when applying
 * a code is the same wording they would see if it lapsed before they paid.
 */
class ApplyCouponRequest extends FormRequest
{
    use CheckoutValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => $this->couponCodeRules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateCoupon($validator);
            },
        ];
    }

    public function code(): string
    {
        return mb_strtoupper(trim((string) $this->input('code')));
    }

    protected function validateCoupon(Validator $validator): void
    {
        $coupon = $this->sessionCoupon($this->code());

        if ($coupon === null) {
            // Deliberately the same message as a lapsed code: telling a stranger
            // which codes exist is an invitation to go looking for more.
            $validator->errors()->add('code', __('That code is not valid.'));

            return;
        }

        $storefront = app(StorefrontSession::class);
        $quote = app(CheckoutPricer::class)->quote($storefront->cartLines());

        $reason = $coupon->validateFor($this->user(), $quote->totals->subtotalCents);

        if ($reason !== null) {
            $validator->errors()->add('code', $reason);
        }
    }
}
