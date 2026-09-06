<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\ReviewAuthorFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Stock defaults and review moderation.
 *
 * Booleans arrive from checkboxes, which submit nothing at all when unticked,
 * so each one is `nullable` here and read back through `boolean()` — a missing
 * key is a deliberate "off", not a malformed request.
 */
class UpdateCatalogSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'track_stock_by_default' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['required', 'integer', 'between:0,10000'],
            'out_of_stock_behavior' => ['required', Rule::in(['hide', 'show', 'show_unavailable'])],
            'allow_backorders_by_default' => ['nullable', 'boolean'],

            'reviews_enabled' => ['nullable', 'boolean'],
            'require_verified_purchase' => ['nullable', 'boolean'],
            'auto_approve' => ['nullable', 'boolean'],
            'author_display_format' => ['required', Rule::enum(ReviewAuthorFormat::class)],
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function inventoryValues(): array
    {
        return [
            'track_stock_by_default' => $this->boolean('track_stock_by_default'),
            'low_stock_threshold' => $this->integer('low_stock_threshold'),
            'out_of_stock_behavior' => $this->string('out_of_stock_behavior')->value(),
            'allow_backorders_by_default' => $this->boolean('allow_backorders_by_default'),
        ];
    }

    /**
     * @return array<string, bool|string>
     */
    public function reviewValues(): array
    {
        return [
            'reviews_enabled' => $this->boolean('reviews_enabled'),
            'require_verified_purchase' => $this->boolean('require_verified_purchase'),
            'auto_approve' => $this->boolean('auto_approve'),
            'author_display_format' => $this->string('author_display_format')->value(),
        ];
    }
}
