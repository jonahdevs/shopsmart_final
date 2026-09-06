<?php

namespace App\Http\Requests\Admin\Settings;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * How the store presents itself to search engines.
 */
class UpdateSeoSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meta_title_pattern' => ['required', 'string', 'max:255', $this->containsPagePlaceholder()],
            'default_meta_description' => ['nullable', 'string', 'max:320'],
            'index_site' => ['nullable', 'boolean'],
            'generate_sitemap' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, bool|string>
     */
    public function seoValues(): array
    {
        return [
            'meta_title_pattern' => $this->string('meta_title_pattern')->trim()->value(),
            'default_meta_description' => $this->string('default_meta_description')->trim()->value(),
            'index_site' => $this->boolean('index_site'),
            'generate_sitemap' => $this->boolean('generate_sitemap'),
        ];
    }

    /**
     * A pattern with no `{page}` in it renders the same title on every page,
     * which is the single most common way to lose search visibility.
     */
    private function containsPagePlaceholder(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ! Str::contains($value, '{page}')) {
                $fail(__('The title pattern must contain the :placeholder placeholder.', ['placeholder' => '{page}']));
            }
        };
    }
}
