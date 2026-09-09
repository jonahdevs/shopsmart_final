<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * One bulk click from the products table, validated.
 *
 * This is the security boundary of the whole feature. The body arrives as a
 * list of ids the browser chose, and the browser is not trusted about any of
 * them: not that they exist, not how many there are, and — for a restore — not
 * that they are in the bin. Every one of those is checked here rather than in
 * the controller, so a crafted request fails before a single row is touched
 * and nothing partial is applied.
 *
 * Eligibility is a different question and deliberately not answered here: a
 * product that is already published, or one sitting in the bin when somebody
 * asks to publish it, is a well-formed request about a real row. The controller
 * re-reads those rows and reports them per id. Turning them into a 422 would
 * throw away the twenty-four products that were fine.
 */
class ProductBulkActionRequest extends FormRequest
{
    /**
     * The most ids one request may carry.
     *
     * Selection in the table is scoped to the page on screen, so the UI never
     * sends more ids than the products index puts on a page — twenty-five
     * today. The cap sits well above that, so a larger page size does not
     * silently start failing, and far below "the catalog", so a hand-rolled
     * request cannot bin every product in one call. Without it `ids` is
     * unbounded and one POST is a catalog-wide delete.
     */
    public const MAX_IDS = 100;

    /**
     * What may be done to a selection.
     *
     * Assigning a brand, a category or a tax class in bulk is deliberately not
     * here yet: each needs its own picker and its own eligibility rules, and a
     * closed list is the thing that makes adding one a decision rather than an
     * accident.
     *
     * @var list<string>
     */
    public const ACTIONS = ['status', 'delete', 'restore'];

    /**
     * The statuses a selection may be set to.
     *
     * Three of the four cases. `Scheduled` is missing on purpose — it is only
     * meaningful alongside a `published_at`, and a bulk bar has nowhere honest
     * to ask for one. Setting twenty-five products to "scheduled" with no date
     * would leave them invisible with no indication of when that ends.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_map(
            fn (ProductStatus $status): string => $status->value,
            [ProductStatus::Draft, ProductStatus::Published, ProductStatus::Archived],
        );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            // `distinct` matters more than it looks: a repeated id would be
            // acted on twice and counted twice, so the summary would describe
            // more work than was done.
            'ids.*' => ['required', 'integer', 'distinct', $this->idExists()],
            'status' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === 'status'),
                Rule::in(self::statuses()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.max' => __('Too many products were selected at once. Select :count or fewer.', ['count' => self::MAX_IDS]),
            'ids.*.exists' => __('One of the selected products no longer exists.'),
        ];
    }

    /**
     * The ids, as integers.
     *
     * @return list<int>
     */
    public function productIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_map(intval(...), $ids));
    }

    public function action(): string
    {
        /** @var string $action */
        $action = $this->validated('action');

        return $action;
    }

    public function status(): ProductStatus
    {
        /** @var string $status */
        $status = $this->validated('status');

        return ProductStatus::from($status);
    }

    /**
     * Every id must name a real product row.
     *
     * `exists` reads the table directly rather than through the model, so a
     * soft-deleted product still satisfies it — which is what the status and
     * delete actions want, because the table can be showing the bin and the
     * controller has something meaningful to say about those rows.
     *
     * Restore is the exception and the reason this is a method: restoring a
     * product that was never deleted is not a no-op worth reporting, it is a
     * request that makes no sense, so the row must actually be in the bin.
     * Anything else is rejected outright.
     */
    private function idExists(): Exists
    {
        $exists = Rule::exists('products', 'id');

        return $this->input('action') === 'restore'
            ? $exists->whereNotNull('deleted_at')
            : $exists;
    }
}
