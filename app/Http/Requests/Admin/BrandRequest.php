<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Everything the brand editor may send, validated once for both create and
 * edit. The two differ only in which row uniqueness ignores.
 */
abstract class BrandRequest extends FormRequest
{
    /** The brand being edited, or null when one is being created. */
    abstract protected function brand(): ?Brand;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $brandId = $this->brand()?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('brands', 'slug')->ignore($brandId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function brandAttributes(): array
    {
        $slug = $this->nullableString('slug');

        return [
            'name' => (string) $this->validated('name'),
            'slug' => $slug ?? $this->uniqueSlugFromName(),
            'description' => $this->nullableString('description'),
            'website_url' => $this->nullableString('website_url'),
            'is_active' => (bool) $this->validated('is_active'),
            'sort_order' => $this->nullableInt('sort_order') ?? 0,
            'meta_title' => $this->nullableString('meta_title'),
            'meta_description' => $this->nullableString('meta_description'),
        ];
    }

    private function uniqueSlugFromName(): string
    {
        $base = Str::slug((string) $this->validated('name'));
        $base = $base === '' ? 'brand' : $base;
        $slug = $base;
        $suffix = 2;

        while (Brand::query()
            ->where('slug', $slug)
            ->whereKeyNot($this->brand()?->getKey() ?? 0)
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

    private function nullableInt(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
