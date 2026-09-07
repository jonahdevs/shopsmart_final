<?php

namespace App\Http\Requests\Admin;

use App\Models\TaxClass;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Everything the tax class editor may send, validated once for both create and
 * edit. The two differ only in which row uniqueness ignores.
 *
 * `rate` is a percentage, not money: the column is `decimal(5, 2)` and holds
 * "16.00" for Kenyan standard VAT, never 1600 cents. So it is validated as a
 * plain number with at most two decimal places rather than converted through
 * {@see Money}, and the ceiling is 100 — the column would accept
 * 999.99, but a band charging more than the goods cost is a typo every time.
 */
abstract class TaxClassRequest extends FormRequest
{
    /** The tax class being edited, or null when one is being created. */
    abstract protected function taxClass(): ?TaxClass;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taxClassId = $this->taxClass()?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('tax_classes', 'slug')->ignore($taxClassId)],
            'rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.decimal' => __('A tax rate may have at most two decimal places.'),
            'rate.max' => __('A tax rate cannot exceed 100%.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function taxClassAttributes(): array
    {
        $slug = $this->nullableString('slug');

        return [
            'name' => (string) $this->validated('name'),
            'slug' => $slug ?? $this->uniqueSlugFromName(),
            // Normalised before it reaches the column so "16" and "16.0" both
            // land as "16.00" and the edit form redisplays what was saved.
            'rate' => number_format((float) $this->validated('rate'), 2, '.', ''),
            'description' => $this->nullableString('description'),
            'is_active' => (bool) $this->validated('is_active'),
        ];
    }

    private function uniqueSlugFromName(): string
    {
        $base = Str::slug((string) $this->validated('name'));
        $base = $base === '' ? 'tax-class' : $base;
        $slug = $base;
        $suffix = 2;

        while (TaxClass::query()
            ->where('slug', $slug)
            ->whereKeyNot($this->taxClass()?->getKey() ?? 0)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
