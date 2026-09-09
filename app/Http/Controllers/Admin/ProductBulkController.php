<?php

namespace App\Http\Controllers\Admin;

use App\Data\BulkActionOutcomeData;
use App\Data\BulkActionResultData;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductBulkActionRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * One action applied to a selection of products.
 *
 * Separate from {@see ProductController} because it answers a different shape
 * of question. Every other action there is about one product bound by slug and
 * either succeeds or 404s; this one is handed a list, decides each id on its
 * own, and its interesting output is the disagreement between them.
 *
 * Two rules hold this together and both are worth stating out loud:
 *
 * 1. **Nothing the client sent about a row is believed.** The browser drew
 *    those checkboxes off a page of props that may be minutes old — a product
 *    can have been published, or binned, by somebody else in the meantime. So
 *    the rows are re-read here and eligibility is recomputed from the database
 *    per id. A `canPublish` flag posted by the client would be a fiction the
 *    server agreed to.
 *
 * 2. **Rows are changed one at a time, through the model.** A single
 *    `->update()` on the query builder would be one statement and would write
 *    no activity at all: `LogsActivity` hangs off model events, which a mass
 *    update does not fire. Twenty-five products changed by one click are
 *    twenty-five things that happened to the catalog, and the log has to say
 *    so — that is the record an audit reads. The extra queries are the price
 *    of the audit trail and are bounded by
 *    {@see ProductBulkActionRequest::MAX_IDS}.
 */
class ProductBulkController extends Controller
{
    public function __invoke(ProductBulkActionRequest $request): RedirectResponse
    {
        $action = $request->action();

        $result = DB::transaction(fn (): BulkActionResultData => $this->apply(
            $action,
            $this->rowsToActOn($request->productIds()),
            $action === 'status' ? $request->status() : null,
        ));

        Inertia::flash('toast', [
            // A partial result is not a success. Staff learn to stop reading a
            // green toast, so anything skipped or refused arrives in a colour
            // that says "there is something here to look at" and the detail
            // strip on the index says what.
            'type' => $result->isClean ? 'success' : 'warning',
            'message' => $result->summary,
        ]);

        // Back rather than to the index, so the filter, sort and page the staff
        // member was working in survive the action. The result rides the
        // session because it is a one-off answer to one click: it must not be
        // re-shown when they later sort the same table.
        return back()->with('bulkResult', $result);
    }

    /**
     * The selected products, re-read from the database.
     *
     * `withTrashed()` because the table can be showing the bin, and the row
     * being binned is exactly what the restore action acts on and what the
     * status action must refuse. Ordered by name so the skipped and refused
     * lists read in the order a staff member would scan for them, rather than
     * in whatever order the ids arrived.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function rowsToActOn(array $ids): Collection
    {
        return Product::query()
            ->withTrashed()
            ->whereKey($ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function apply(string $action, Collection $products, ?ProductStatus $status): BulkActionResultData
    {
        $appliedCount = 0;
        /** @var list<BulkActionOutcomeData> $skipped */
        $skipped = [];
        /** @var list<BulkActionOutcomeData> $refused */
        $refused = [];

        foreach ($products as $product) {
            $outcome = match ($action) {
                'status' => $this->setStatus($product, $status ?? ProductStatus::Draft),
                'delete' => $this->moveToBin($product),
                default => $this->restore($product),
            };

            if ($outcome === null) {
                $appliedCount++;

                continue;
            }

            $entry = new BulkActionOutcomeData(
                id: $product->getKey(),
                label: $product->name,
                reason: $outcome['reason'],
            );

            if ($outcome['bucket'] === 'refused') {
                $refused[] = $entry;
            } else {
                $skipped[] = $entry;
            }
        }

        return BulkActionResultData::make(
            match ($action) {
                'status' => __('updated'),
                'delete' => __('moved to the bin'),
                default => __('restored'),
            },
            $appliedCount,
            $skipped,
            $refused,
        );
    }

    /**
     * @return array{bucket: 'skipped'|'refused', reason: string}|null Null when applied.
     */
    private function setStatus(Product $product, ProductStatus $status): ?array
    {
        if ($product->trashed()) {
            // Refused, not skipped: a product in the bin is not published,
            // draft or archived — it is out of the catalog entirely, and
            // quietly writing a status onto it would leave the staff member
            // believing they had published something that is still invisible.
            return ['bucket' => 'refused', 'reason' => __('In the bin — restore it first.')];
        }

        if ($product->status === $status) {
            // `logOnlyDirty()` would write nothing here anyway, so returning
            // early only avoids a pointless UPDATE. Reporting it is the point:
            // "18 updated, 4 skipped" tells the reader their selection was
            // wider than the work, which is usually what they wanted to know.
            return ['bucket' => 'skipped', 'reason' => __('Already :status.', ['status' => mb_strtolower($status->label())])];
        }

        $product->update(['status' => $status]);

        return null;
    }

    /**
     * @return array{bucket: 'skipped'|'refused', reason: string}|null Null when applied.
     */
    private function moveToBin(Product $product): ?array
    {
        if ($product->trashed()) {
            return ['bucket' => 'skipped', 'reason' => __('Already in the bin.')];
        }

        $product->delete();

        return null;
    }

    /**
     * @return array{bucket: 'skipped'|'refused', reason: string}|null Null when applied.
     */
    private function restore(Product $product): ?array
    {
        // The request already refuses a restore whose ids are not all trashed,
        // so this only catches the race where somebody restored the row between
        // validation and here. Cheap, and it keeps the method honest on its own
        // terms rather than depending on a rule two files away.
        if (! $product->trashed()) {
            return ['bucket' => 'skipped', 'reason' => __('Not in the bin.')];
        }

        $product->restore();

        return null;
    }
}
