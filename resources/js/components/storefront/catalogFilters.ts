import type { QueryParams } from '@/wayfinder';

/**
 * The catalog listing's client-side filter vocabulary.
 *
 * The server echoes its own reading of the query string back as
 * `CatalogFilterData`, so the client never re-parses the URL: every change is a
 * patch applied over that echo and turned back into a complete query string.
 * That is what makes a facet click preserve every other active filter, and what
 * lets "load more" carry the same filters into the next page.
 */
export type CatalogFilterPatch = {
    q?: string;
    categories?: string[];
    brands?: number[];
    priceMin?: number;
    /** Null means "no upper bound", which is a different thing from the ceiling. */
    priceMax?: number | null;
    inStockOnly?: boolean;
    minRating?: number;
    tag?: string;
    newArrivalsOnly?: boolean;
    sort?: string;
};

/**
 * The sorts the server actually implements. Mirrors both
 * `FiltersCatalogProducts::applySort()` and the `in:` rule on `sort` — anything
 * else is rejected by the form request before it reaches the listing.
 */
export const CATALOG_SORTS = [
    { value: 'popularity', label: 'Popularity' },
    { value: 'newest', label: 'Newest first' },
    { value: 'name-asc', label: 'Name A–Z' },
    { value: 'price-asc', label: 'Price: low to high' },
    { value: 'price-desc', label: 'Price: high to low' },
] as const;

/** The server's `sort` default, so it never needs to appear in a URL. */
export const DEFAULT_SORT = 'popularity';

/**
 * The minimum-rating steps offered in the sidebar. Five is deliberately absent:
 * "5 & up" is an exact match dressed up as a range.
 */
export const RATING_STEPS = [4, 3, 2, 1] as const;

/**
 * Fold a patch over the server's filter echo and emit the complete query
 * string for the next visit.
 *
 * Every value that equals the server's own default is left out entirely, so a
 * shopper who has touched nothing lands on a clean `/shop` rather than a URL
 * restating the defaults back at itself.
 */
export function catalogQuery(
    filters: App.Data.CatalogFilterData,
    patch: CatalogFilterPatch = {},
    page = 1,
): QueryParams {
    const q = patch.q ?? filters.q;
    const categories = patch.categories ?? filters.categories;
    const brands = patch.brands ?? filters.brands;
    const priceMin = patch.priceMin ?? filters.priceMin;
    // `??` would swallow a deliberate `null`, which is exactly how the upper
    // bound is cleared, so the key's presence decides rather than its value.
    const priceMax =
        patch.priceMax !== undefined ? patch.priceMax : filters.priceMax;
    const inStockOnly = patch.inStockOnly ?? filters.inStockOnly;
    const minRating = patch.minRating ?? filters.minRating;
    const tag = patch.tag ?? filters.tag;
    const newArrivalsOnly = patch.newArrivalsOnly ?? filters.newArrivalsOnly;
    const sort = patch.sort ?? filters.sort;

    return {
        q: q === '' ? undefined : q,
        cat: categories.length > 0 ? categories : undefined,
        brand: brands.length > 0 ? brands : undefined,
        pmin: priceMin > 0 ? priceMin : undefined,
        pmax: priceMax ?? undefined,
        stock: inStockOnly ? 1 : undefined,
        rating: minRating > 0 ? minRating : undefined,
        tag: tag === '' ? undefined : tag,
        arrivals: newArrivalsOnly ? 1 : undefined,
        sort: sort === DEFAULT_SORT ? undefined : sort,
        page: page > 1 ? page : undefined,
    };
}

/** The patch that returns a listing to an untouched state. */
export function clearedFilters(): CatalogFilterPatch {
    return {
        q: '',
        categories: [],
        brands: [],
        priceMin: 0,
        priceMax: null,
        inStockOnly: false,
        minRating: 0,
        tag: '',
        newArrivalsOnly: false,
    };
}

/** Add or remove one value from a facet's selection, without mutating it. */
export function toggleValue<T>(values: readonly T[], value: T): T[] {
    return values.includes(value)
        ? values.filter((candidate) => candidate !== value)
        : [...values, value];
}

/**
 * Group a whole number for display.
 *
 * Currency is never formatted on the client — money arrives preformatted from
 * the server and is rendered through `Price.vue`. A filter bound is not money:
 * it is a bare number the shopper typed, so it carries no symbol here either.
 */
export function groupNumber(value: number): string {
    return new Intl.NumberFormat().format(value);
}

/** Whether either end of the price range is actually narrowing the listing. */
export function hasPriceBound(filters: App.Data.CatalogFilterData): boolean {
    return filters.priceMin > 0 || filters.priceMax !== null;
}

/** The human label for a price range that may be open at either end. */
export function priceRangeLabel(
    filters: App.Data.CatalogFilterData,
): string | null {
    const { priceMin, priceMax } = filters;

    if (priceMin > 0 && priceMax !== null) {
        return `Price ${groupNumber(priceMin)}–${groupNumber(priceMax)}`;
    }

    if (priceMin > 0) {
        return `Price from ${groupNumber(priceMin)}`;
    }

    if (priceMax !== null) {
        return `Price up to ${groupNumber(priceMax)}`;
    }

    return null;
}
