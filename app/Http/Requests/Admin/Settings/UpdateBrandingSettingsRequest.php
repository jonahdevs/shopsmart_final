<?php

namespace App\Http\Requests\Admin\Settings;

use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store identity and the social profiles rendered in the footer.
 *
 * Every URL here ends up in an `href`, so each one is checked through
 * {@see SafeUrl} rather than the `url` rule: that both rejects a `javascript:`
 * target and allows the same-site paths the store legitimately uses.
 */
class UpdateBrandingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'favicon_path' => ['nullable', 'string', 'max:2048'],

            'og_image_path' => ['nullable', 'string', 'max:2048'],
            'twitter_handle' => ['nullable', 'string', 'max:50'],
            'facebook_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],
            'instagram_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],
            'x_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],
            'linkedin_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],
            'youtube_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_order_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function brandingValues(): array
    {
        return [
            'store_name' => $this->text('store_name'),
            'tagline' => $this->text('tagline'),
            'logo_path' => $this->nullableText('logo_path'),
            'favicon_path' => $this->nullableText('favicon_path'),
        ];
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function socialValues(): array
    {
        return [
            'og_image_path' => $this->nullableText('og_image_path'),
            'twitter_handle' => ltrim($this->text('twitter_handle'), '@'),
            'facebook_url' => $this->text('facebook_url'),
            'instagram_url' => $this->text('instagram_url'),
            'x_url' => $this->text('x_url'),
            'linkedin_url' => $this->text('linkedin_url'),
            'youtube_url' => $this->text('youtube_url'),
            'whatsapp_number' => $this->text('whatsapp_number'),
            'whatsapp_order_enabled' => $this->boolean('whatsapp_order_enabled'),
        ];
    }

    /**
     * Rejects anything {@see SafeUrl} would refuse to put behind a link.
     */
    private function safeUrl(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && app(SafeUrl::class)->forLink($value) === null) {
                $fail(__('The :attribute must be a full http(s) address or a path beginning with /.'));
            }
        };
    }

    private function text(string $key): string
    {
        return $this->string($key)->trim()->value();
    }

    private function nullableText(string $key): ?string
    {
        return $this->text($key) ?: null;
    }
}
