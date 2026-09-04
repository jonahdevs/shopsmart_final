<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One rung of a storefront breadcrumb trail. The slug is null for a rung that
 * is not a category (for example the "Shop" root), so the client can decide
 * whether to link it.
 */
#[TypeScript]
class BreadcrumbData extends Data
{
    public function __construct(
        public string $name,
        public ?string $slug,
    ) {}
}
