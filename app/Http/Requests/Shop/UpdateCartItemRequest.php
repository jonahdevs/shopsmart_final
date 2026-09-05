<?php

namespace App\Http\Requests\Shop;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Setting a cart line to an exact quantity.
 *
 * Zero is allowed and means "remove this line" — that is what a stepper stepped
 * down past one sends — and it skips the purchasability check, because getting
 * rid of something that is no longer for sale must always work.
 */
class UpdateCartItemRequest extends AddToCartRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => $this->productIdRules(),
            'variant_id' => $this->variantIdRules(),
            'quantity' => [...$this->quantityRules(min: 0), 'required'],
        ];
    }

    public function quantity(): int
    {
        return max(0, $this->integer('quantity'));
    }

    protected function validatePurchasable(Validator $validator): void
    {
        if ($this->quantity() === 0) {
            return;
        }

        parent::validatePurchasable($validator);
    }
}
