<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReviewStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A moderator's decision on one review.
 *
 * Only the two decisions a moderator actually makes are accepted.
 * {@see ReviewStatus::Pending} is the state a review arrives in, not one a
 * human puts it into, and offering it would let staff quietly un-decide a
 * review that a shopper has already seen published.
 */
class UpdateReviewStatusRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([ReviewStatus::Approved->value, ReviewStatus::Rejected->value]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('A review can only be approved or rejected.'),
        ];
    }
}
