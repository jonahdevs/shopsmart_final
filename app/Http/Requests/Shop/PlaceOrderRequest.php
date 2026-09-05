<?php

namespace App\Http\Requests\Shop;

use App\Concerns\CheckoutValidationRules;
use App\Enums\DeliveryMethod;
use App\Settings\ShippingSettings;
use App\Support\CheckoutPricer;
use App\Support\StorefrontSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The order the shopper is asking to place.
 *
 * `quoted_total_cents` is the total the page displayed. It is checked against a
 * freshly computed quote, so a catalog price that moved between the page
 * rendering and the button being pressed stops the order rather than silently
 * charging a different number. It is a confirmation of what was seen, never a
 * source of the price.
 */
class PlaceOrderRequest extends FormRequest
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
            'delivery_method' => $this->deliveryMethodRules(),
            'address_id' => $this->addressIdRules(),
            'customer_note' => $this->customerNoteRules(),
            'quoted_total_cents' => ['required', 'integer', 'min:0'],
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

                $this->validateOrderable($validator);
            },
        ];
    }

    public function customerNote(): ?string
    {
        $note = $this->input('customer_note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }

    public function quotedTotalCents(): int
    {
        return $this->integer('quoted_total_cents');
    }

    /**
     * Everything that has to be true of the cart, not of the form.
     *
     * Runs in cheapest-first order and stops at the first failure, so a shopper
     * with an empty cart is not also told their delivery choice is unavailable.
     */
    protected function validateOrderable(Validator $validator): void
    {
        $storefront = app(StorefrontSession::class);
        $lines = $storefront->cartLines();

        if ($lines === []) {
            $validator->errors()->add('cart', __('Your cart is empty.'));

            return;
        }

        $delivery = $this->deliveryMethod();

        if ($delivery === DeliveryMethod::Pickup && ! app(ShippingSettings::class)->local_pickup_enabled) {
            $validator->errors()->add('delivery_method', __('Collection is not available at the moment.'));

            return;
        }

        if ($delivery->requiresAddress() && $this->address() === null) {
            $validator->errors()->add('address_id', __('Choose where this order should be delivered.'));

            return;
        }

        $coupon = $this->sessionCoupon($storefront->couponCode());
        $quote = app(CheckoutPricer::class)->quote($lines, $coupon, $delivery);

        // Re-checked here rather than trusted from the page, because the coupon
        // may have lapsed or been fully redeemed while the shopper was deciding.
        if ($coupon !== null) {
            $reason = $coupon->validateFor($this->user(), $quote->totals->subtotalCents);

            if ($reason !== null) {
                $validator->errors()->add('coupon', $reason);

                return;
            }
        }

        foreach ($quote->blockers as $blocker) {
            $validator->errors()->add('cart', $blocker);
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($quote->totals->totalCents !== $this->quotedTotalCents()) {
            $validator->errors()->add('quoted_total_cents', __(
                'Prices changed while you were checking out. The total is now :amount — please review before paying.',
                ['amount' => $quote->totals->totalFormatted],
            ));
        }
    }
}
