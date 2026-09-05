<?php

namespace App\Http\Requests\Shop;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Removing one cart line.
 *
 * Deliberately validates ids and nothing else: the whole point of this endpoint
 * is to get rid of a line, and a line whose product has since been deleted,
 * archived or hidden is exactly the one a shopper most needs to remove. An
 * id that matches nothing simply removes nothing.
 */
class RemoveCartItemRequest extends FormRequest
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
            'variant_id' => ['nullable', 'integer'],
        ];
    }

    public function productId(): int
    {
        return $this->integer('product_id');
    }

    public function variantId(): ?int
    {
        return $this->input('variant_id') === null ? null : $this->integer('variant_id');
    }
}
