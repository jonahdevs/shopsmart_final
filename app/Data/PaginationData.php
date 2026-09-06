<?php

namespace App\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The page counters an admin table needs, without the rows.
 *
 * Laravel's own paginator serialises its links as absolute URLs carrying
 * whatever query string produced them. The admin tables drive their own
 * paging through Wayfinder, so they need the numbers and not the URLs —
 * shipping both would mean two sources of truth for "which page is this".
 *
 * The storefront listings deliberately do NOT use this: they page by "load
 * more" and only ever ask whether another page exists.
 */
#[TypeScript]
class PaginationData extends Data
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        /** 1-based index of the first row on this page; 0 when empty. */
        public int $from,
        public int $to,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, covariant mixed>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            currentPage: $paginator->currentPage(),
            lastPage: max(1, $paginator->lastPage()),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            from: $paginator->firstItem() ?? 0,
            to: $paginator->lastItem() ?? 0,
        );
    }
}
