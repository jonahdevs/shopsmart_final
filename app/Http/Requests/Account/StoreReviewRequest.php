<?php

namespace App\Http\Requests\Account;

use App\Concerns\ReviewValidationRules;
use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\User;
use App\Support\ReviewEligibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A customer writing about something they bought.
 *
 * Two conditions decide whether the review may exist at all, and both are
 * checked here rather than in the controller: the shopper must have received
 * the product on a completed order, and must not already have reviewed it.
 * Neither is expressible as a field rule, so both live in {@see after()} — the
 * shopper gets a sentence explaining the refusal instead of a bare 403.
 *
 * `user_id`, `author_name`, `status` and `verified_purchase` are never read
 * from the request. They are decided here, from the session and from the
 * purchase that was just verified.
 */
class StoreReviewRequest extends FormRequest
{
    use ReviewValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reviewRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $product = $this->product();

                if (! $user instanceof User) {
                    return;
                }

                $eligibility = app(ReviewEligibility::class);

                if (! $eligibility->enabled()) {
                    $validator->errors()->add('body', __('Reviews are closed at the moment.'));

                    return;
                }

                if ($eligibility->hasReviewed($user, $product)) {
                    $validator->errors()->add('body', __('You have already reviewed this product.'));

                    return;
                }

                // Only asked while the store requires proof of purchase; the
                // setting is authoritative, so turning it off genuinely opens
                // reviews to any signed-in customer.
                if (! $eligibility->canReview($user, $product)) {
                    $validator->errors()->add('body', __('You can only review a product once an order containing it has been delivered.'));
                }
            },
        ];
    }

    /** The product this review is about, from the route binding. */
    public function product(): Product
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $product;
    }

    /**
     * The row to insert.
     *
     * Deliberately not named `attributes()`: that is FormRequest's hook for
     * custom validation-message names, and overriding it here would silently
     * break every message this request produces.
     *
     * The author's name is snapshotted so the review survives the account being
     * deleted, and the review goes into the queue rather than straight onto the
     * product page — phase 7 owns the moderation screen that clears it.
     *
     * @return array<string, mixed>
     */
    public function reviewAttributes(): array
    {
        $eligibility = app(ReviewEligibility::class);

        // Auto-approval is a store setting, so a review can publish on the spot.
        // The moderated default stays the safer one.
        $approved = $eligibility->publishesImmediately();

        return [
            'product_id' => $this->product()->getKey(),
            'user_id' => $this->user()?->getKey(),
            'author_name' => (string) $this->user()?->name,
            'rating' => $this->integer('rating'),
            'title' => $this->string('title')->trim()->value() ?: null,
            'body' => $this->string('body')->trim()->value(),
            'status' => $approved ? ReviewStatus::Approved : ReviewStatus::Pending,
            // Never read from the request. It records whether this reviewer
            // actually received the product — which stays meaningful even when
            // the store has stopped requiring it, because the badge on a review
            // is exactly the thing a shopper is weighing.
            'verified_purchase' => $this->user() instanceof User
                && $eligibility->hasDeliveredPurchase($this->user(), $this->product()),
            'approved_at' => $approved ? now() : null,
        ];
    }
}
