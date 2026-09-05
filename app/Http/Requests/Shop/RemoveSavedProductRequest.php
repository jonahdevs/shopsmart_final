<?php

namespace App\Http\Requests\Shop;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Removing a product from the wishlist or the compare tray.
 *
 * Validates the id and nothing else, for the same reason
 * {@see RemoveCartItemRequest} does: unsaving something that has since left the
 * storefront has to keep working.
 */
class RemoveSavedProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
        ];
    }

    public function productId(): int
    {
        return $this->integer('product_id');
    }
}
