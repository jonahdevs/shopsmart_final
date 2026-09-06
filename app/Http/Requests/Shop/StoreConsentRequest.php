<?php

namespace App\Http\Requests\Shop;

use App\Support\Consent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The visitor answering the cookie banner.
 *
 * Three answers reach the same endpoint, told apart by the submit button that
 * sent them: accept everything offered, accept nothing, or accept the boxes
 * that were ticked. Inertia's `<Form>` builds its payload with
 * `new FormData(form, submitter)`, so the button's own name and value travel
 * with the request.
 */
class StoreConsentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accept' => ['required', Rule::in(['all', 'none', 'selected'])],
            'categories' => ['array'],
            'categories.*' => [Rule::in(app(Consent::class)->offeredValues())],
        ];
    }

    /**
     * The categories to grant.
     *
     * Filtered against what the store currently offers rather than trusted from
     * the form, so a category that was withdrawn in the admin between the page
     * being rendered and the banner being answered cannot be granted.
     *
     * @return list<string>
     */
    public function grantedCategories(): array
    {
        $consent = app(Consent::class);

        return match ($this->string('accept')->value()) {
            'all' => $consent->offeredValues(),
            'none' => [],
            default => array_values(array_intersect(
                $consent->offeredValues(),
                array_values(array_filter((array) $this->input('categories', []), 'is_string')),
            )),
        };
    }
}
