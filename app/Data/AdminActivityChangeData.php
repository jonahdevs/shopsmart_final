<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One attribute that moved, as the audit trail prints it.
 *
 * `from` and `to` are strings the page shows verbatim — the formatting is done
 * here so no Vue component has to know that `total_cents` is money or that a
 * missing value means "not set". They are null when the viewer may see that an
 * attribute changed but not what it changed to; see {@see AdminActivityRowData}.
 */
#[TypeScript]
class AdminActivityChangeData extends Data
{
    public function __construct(
        public string $attribute,
        public string $label,
        public ?string $from,
        public ?string $to,
    ) {}
}
