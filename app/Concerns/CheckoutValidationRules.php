<?php

namespace App\Concerns;

use App\Enums\DeliveryMethod;
use App\Models\Address;
use App\Models\Coupon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Rule fragments and memoised lookups shared by the checkout requests.
 *
 * The resolved-model accessors are public because the controller reads them
 * too: resolving once on the request means the validator and the action work
 * from the same row rather than querying for it twice.
 */
trait CheckoutValidationRules
{
    private ?Address $resolvedAddress = null;

    private bool $addressResolved = false;

    private ?Coupon $resolvedCoupon = null;

    private bool $couponResolved = false;

    /**
     * The address must belong to the signed-in customer, which `exists` alone
     * would not enforce — an id from someone else's book would otherwise pass.
     *
     * @return list<ValidationRule|string>
     */
    protected function addressIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('addresses', 'id')->where('user_id', $this->user()?->getKey()),
        ];
    }

    /** @return list<ValidationRule|string> */
    protected function deliveryMethodRules(): array
    {
        return ['required', 'string', new Enum(DeliveryMethod::class)];
    }

    /** @return list<ValidationRule|string> */
    protected function couponCodeRules(): array
    {
        return ['required', 'string', 'max:64'];
    }

    /** @return list<ValidationRule|string> */
    protected function customerNoteRules(): array
    {
        return ['nullable', 'string', 'max:1000'];
    }

    /**
     * The whole address, for the "deliver somewhere new" path.
     *
     * @return array<string, list<ValidationRule|string>>
     */
    protected function addressRules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function deliveryMethod(): DeliveryMethod
    {
        return DeliveryMethod::from((string) $this->input('delivery_method', DeliveryMethod::Delivery->value));
    }

    /**
     * The chosen address, or null when none was sent.
     *
     * The boolean flag sits beside the value so a legitimately-null result is
     * not re-queried on every call.
     */
    public function address(): ?Address
    {
        if ($this->addressResolved) {
            return $this->resolvedAddress;
        }

        $this->addressResolved = true;

        $id = $this->input('address_id');

        $this->resolvedAddress = $id === null
            ? null
            : Address::query()->whereKey((int) $id)->first();

        return $this->resolvedAddress;
    }

    /** The coupon held in session, resolved once. */
    public function sessionCoupon(?string $code): ?Coupon
    {
        if ($this->couponResolved) {
            return $this->resolvedCoupon;
        }

        $this->couponResolved = true;

        $this->resolvedCoupon = $code === null
            ? null
            : Coupon::query()->where('code', mb_strtoupper($code))->first();

        return $this->resolvedCoupon;
    }
}
