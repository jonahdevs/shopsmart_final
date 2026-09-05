<?php

namespace App\Http\Requests\Shop;

use App\Concerns\StorefrontProductValidationRules;
use App\Enums\ProductVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Saving a product to the wishlist or the compare tray.
 *
 * A weaker bar than adding to a cart: something out of stock, or priced on
 * application, is exactly the kind of thing shoppers save for later. It only has
 * to be a product they could have reached — live, and not hidden from the
 * storefront.
 */
class SaveProductRequest extends FormRequest
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

                $product = $this->product();

                if ($product === null || ! $product->isPublished() || $product->visibility === ProductVisibility::Hidden) {
                    $validator->errors()->add('product_id', __('That product is not available.'));
                }
            },
        ];
    }
}
