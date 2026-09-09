<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row a bulk action did not apply to, and why.
 *
 * The `label` is the whole point. A count of failures a staff member cannot
 * turn back into rows is not a result, it is an apology — they still have to
 * re-select twenty-five products to find the four that did not move. So every
 * entry carries the name the table printed, in the words the reader just saw.
 *
 * The `reason` is staff-facing prose, already translated. It is never a code
 * the client has to interpret: the server is the only place that knows why a
 * row was ineligible, and a client re-deriving that would be the same "trust
 * the flag" mistake bulk endpoints exist to avoid.
 */
#[TypeScript]
class BulkActionOutcomeData extends Data
{
    public function __construct(
        public int $id,
        /** How the row is named on screen, so the reader can find it again. */
        public string $label,
        public string $reason,
    ) {}
}
