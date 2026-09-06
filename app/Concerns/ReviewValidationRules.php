<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The shape of a customer review.
 *
 * Kept apart from the request so the staff-side moderation screens in phase 7
 * can validate an edited review against the same bounds a shopper submitted
 * under, rather than inventing a second set.
 */
trait ReviewValidationRules
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    protected function reviewRules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            // A floor as well as a ceiling: a one-word review tells the next
            // shopper nothing, and the star is already carrying that signal.
            'body' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
