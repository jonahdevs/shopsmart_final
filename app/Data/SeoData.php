<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a crawler should be told about one page.
 *
 * Rendered into the document head by `<x-seo-tags />` in app.blade.php, from
 * the server, on purpose. Inertia's `<Head>` component only reaches the initial
 * HTML when SSR is running; it is off here, so a title set that way is applied
 * by JavaScript after the fact and a crawler reading the raw response sees
 * nothing. Anything a search engine must read lives in this object and is
 * printed by Blade; `<Head>` still owns what the BROWSER TAB says, which is why
 * the two coexist.
 */
#[TypeScript]
class SeoData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd  Structured data blocks, each rendered as its own ld+json script.
     */
    public function __construct(
        /** Already through the store's title pattern — print it verbatim. */
        public string $title,
        public ?string $description,
        public ?string $canonicalUrl,
        /** The robots directive, already resolved against SeoSettings. */
        public string $robots,
        public array $jsonLd,
    ) {}
}
