<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One product being added to a tag.
 *
 * The id comes from a picker the browser drew off props that may be minutes
 * old, so the row is required to still exist here rather than being trusted.
 * A product already carrying the tag is not rejected — the controller answers
 * that, because re-adding one is a harmless request about a real row and
 * `attachTag` is idempotent.
 */
class TagProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function productId(): int
    {
        return (int) $this->validated('product_id');
    }
}
