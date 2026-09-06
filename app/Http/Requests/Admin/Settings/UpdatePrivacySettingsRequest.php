<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\ConsentCategory;
use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Consent, the policies that explain it, retention windows, and the measurement
 * tag ids all of the above gate.
 *
 * The tag ids are format-checked so a mistyped one fails here rather than
 * silently never reporting. They are *not* rejected for belonging to a category
 * the store does not offer — that is a legitimate intermediate state while a
 * store is being set up, and the page says plainly that such a tag will not
 * load.
 */
class UpdatePrivacySettingsRequest extends FormRequest
{
    /**
     * Google's ids are upper-case by convention but are pasted in whatever case
     * they were copied. Normalising before validation means the format rule
     * describes the id rather than the clipboard it came from.
     */
    protected function prepareForValidation(): void
    {
        foreach (['ga4_id', 'gtm_id'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => mb_strtoupper(trim($this->string($key)->value()))]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consent_categories' => ['array'],
            'consent_categories.*' => [Rule::in(ConsentCategory::optionalValues())],

            'privacy_policy_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],
            'terms_url' => ['nullable', 'string', 'max:2048', $this->safeUrl()],

            // Ten years is well past any purpose either trail serves, and zero
            // is the explicit "keep indefinitely" the prune command honours.
            'recently_viewed_retention_days' => ['required', 'integer', 'between:0,3650'],
            'activity_log_retention_days' => ['required', 'integer', 'between:0,3650'],

            'ga4_id' => ['nullable', 'string', 'max:50', 'regex:/^G-[A-Z0-9]+$/'],
            'gtm_id' => ['nullable', 'string', 'max:50', 'regex:/^GTM-[A-Z0-9]+$/'],
            'meta_pixel_id' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ga4_id.regex' => __('A GA4 measurement ID looks like G-XXXXXXXXXX.'),
            'gtm_id.regex' => __('A Google Tag Manager container ID looks like GTM-XXXXXXX.'),
            'meta_pixel_id.regex' => __('A Meta pixel ID is digits only.'),
        ];
    }

    /**
     * @return array<string, array<int, string>|string|int>
     */
    public function legalValues(): array
    {
        return [
            'consent_categories' => $this->consentCategories(),
            'privacy_policy_url' => $this->text('privacy_policy_url'),
            'terms_url' => $this->text('terms_url'),
            'recently_viewed_retention_days' => $this->integer('recently_viewed_retention_days'),
            'activity_log_retention_days' => $this->integer('activity_log_retention_days'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function analyticsValues(): array
    {
        return [
            'ga4_id' => $this->text('ga4_id'),
            'gtm_id' => $this->text('gtm_id'),
            'meta_pixel_id' => $this->text('meta_pixel_id'),
        ];
    }

    /**
     * Kept in the enum's own order so the stored list is stable, which is what
     * lets a visitor's saved answer be compared against it to decide whether
     * the banner has a new question to ask.
     *
     * @return list<string>
     */
    private function consentCategories(): array
    {
        $submitted = array_values(array_filter((array) $this->input('consent_categories', []), 'is_string'));

        return array_values(array_intersect(ConsentCategory::optionalValues(), $submitted));
    }

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
}
