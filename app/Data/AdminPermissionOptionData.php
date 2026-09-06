<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One checkbox in the permission matrix.
 *
 * `holdable` is false for a permission the editor does not hold themselves;
 * the checkbox is then disabled and the server refuses the value anyway, so a
 * page edited in the browser buys nothing.
 */
#[TypeScript]
class AdminPermissionOptionData extends Data
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $granted,
        public bool $holdable,
    ) {}
}
