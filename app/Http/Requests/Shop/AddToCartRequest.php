<?php

namespace App\Http\Requests\Shop;

use App\Concerns\StorefrontProductValidationRules;
use App\Support\StorefrontSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Adding a product — optionally a specific variant — to the cart.
 *
 * The quantity is a request, not an instruction: {@see StorefrontSession}
 * clamps it to the stock rules afterwards. What is rejected outright here is a
 * product nobody should be able to buy at all — draft, hidden, sold out with no
 * backorder, or a variable product with no variant chosen.
 */
class AddToCartRequest extends FormRequest
{
    use StorefrontProductValidationRules;

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
            'quantity' => $this->quantityRules(),
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

                $this->validatePurchasable($validator);
            },
        ];
    }

    public function quantity(): int
    {
        return max(1, $this->integer('quantity', 1));
    }

    /**
     * The `exists` rules have already passed, so a null product here means the
     * row is soft-deleted out from under the request; treat it the same as any
     * other unavailable product.
     */
    protected function validatePurchasable(Validator $validator): void
    {
        $product = $this->product();

        if ($product !== null && $this->wantsVariant() && $this->variant() === null) {
            $validator->errors()->add('variant_id', __('That option is not available.'));

            return;
        }

        if ($product === null || ! app(StorefrontSession::class)->isPurchasable($product, $this->variant())) {
            $validator->errors()->add('product_id', __('That product cannot be added to your cart right now.'));
        }
    }
}
