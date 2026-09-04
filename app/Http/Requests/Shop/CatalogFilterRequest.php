<?php

namespace App\Http\Requests\Shop;

use App\Concerns\CatalogFilterValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CatalogFilterRequest extends FormRequest
{
    use CatalogFilterValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->catalogFilterRules();
    }
}
