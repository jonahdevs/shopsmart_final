<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a bulk action actually did, row by row.
 *
 * A bulk endpoint is handed a list of ids and re-decides eligibility for every
 * one of them, so its answer is almost never "it worked". Three outcomes, and
 * the vocabulary is fixed here because reviews and orders will reuse it:
 *
 * - **applied** — the change was made and written to the activity log.
 * - **skipped** — the row was eligible and there was simply nothing to do:
 *   already published, already in the bin. Nobody was refused anything.
 * - **refused** — the row exists but its state or the actor's permissions say
 *   this change may not happen to it. Something a staff member should read.
 *
 * The split matters because the two read differently to the person who clicked.
 * Collapsing them into "5 failed" makes a no-op look like an error and trains
 * staff to ignore the message.
 *
 * An id that does not exist, or a list over the cap, is not an outcome at all —
 * that is a malformed request, and the form request behind the endpoint rejects
 * the whole thing before a single row is touched. Nothing partial is applied on
 * a request like that, so it never reaches this object.
 */
#[TypeScript]
class BulkActionResultData extends Data
{
    public function __construct(
        public int $appliedCount,
        /** @var list<BulkActionOutcomeData> */
        public array $skipped,
        /** @var list<BulkActionOutcomeData> */
        public array $refused,
        /** One line naming all three figures, e.g. "18 updated, 4 skipped, 3 refused." */
        public string $summary,
        /** False when anything was skipped or refused — the toast picks its tone off this. */
        public bool $isClean,
    ) {}

    /**
     * @param  string  $appliedVerb  Past participle of what was done, e.g. "updated",
     *                               "moved to the bin". Supplied by the caller because
     *                               "18 updated" and "18 restored" are different
     *                               sentences and only the caller knows which it ran.
     * @param  list<BulkActionOutcomeData>  $skipped
     * @param  list<BulkActionOutcomeData>  $refused
     */
    public static function make(string $appliedVerb, int $appliedCount, array $skipped, array $refused): self
    {
        // Zero buckets are left out rather than printed as "0 refused". A staff
        // member reading "3 refused" needs to notice it, and it is much harder
        // to notice a number in a sentence that always contains three of them.
        $parts = [];

        if ($appliedCount > 0) {
            $parts[] = __(':count :verb', ['count' => $appliedCount, 'verb' => $appliedVerb]);
        }

        if ($skipped !== []) {
            $parts[] = __(':count skipped', ['count' => count($skipped)]);
        }

        if ($refused !== []) {
            $parts[] = __(':count refused', ['count' => count($refused)]);
        }

        return new self(
            appliedCount: $appliedCount,
            skipped: $skipped,
            refused: $refused,
            summary: $parts === []
                ? __('Nothing changed.')
                : implode(', ', $parts).'.',
            isClean: $skipped === [] && $refused === [],
        );
    }
}
