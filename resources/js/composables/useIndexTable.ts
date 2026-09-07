import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import type { QueryParams } from '@/wayfinder';

/** The sort state an index controller always sends back. */
export type IndexSortState = {
    sort: string;
    direction: string;
};

export type UseIndexTableOptions<TFields extends Record<string, string>> = {
    /**
     * Turns a query bag into the page's own URL. Always a Wayfinder call —
     * `(query) => adminOrders.url({ query })` — never a hand-built string.
     */
    toUrl: (query: QueryParams) => string;
    /**
     * The server's current sort, read through a getter so it stays reactive
     * across a partial reload. Pass `() => filters`.
     */
    sortState: () => IndexSortState;
    /** The filter inputs and their initial values, already defaulted off props. */
    fields: TFields;
    /** The sort the controller applies when the URL says nothing. */
    defaultSort: { column: string; direction: 'asc' | 'desc' };
    /** Time to wait after the last keystroke before visiting. */
    debounceMs?: number;
};

export type UseIndexTableReturn<TFields extends Record<string, string>> = {
    form: Ref<TFields>;
    /** True when any filter is narrowing the result — drives the empty state. */
    isFiltered: Ref<boolean>;
    activeQuery: (overrides?: QueryParams) => QueryParams;
    hrefForPage: (page: number) => string;
    sortHref: (column: string) => string;
    ariaSort: (column: string) => 'ascending' | 'descending' | 'none';
    clear: () => void;
};

/**
 * Everything an admin index screen does with the query string.
 *
 * Eleven index pages each grew their own `activeQuery` / `sortHref` /
 * `ariaSort` / `hrefForPage` and a 300ms debounced watcher. They were
 * near-identical, which sounds harmless until you want to change how sorting
 * is signalled and it is a ten-file edit — or until one of them quietly drops
 * `preserveState` and its search box starts losing focus mid-word.
 *
 * Three behaviours here are load-bearing and easy to get wrong alone:
 *
 * - Empty filters are omitted, not sent blank. `?status=` on every URL makes
 *   two identical views look like different pages to browser history.
 * - The default sort is omitted too, so the unfiltered table has a clean URL
 *   and the "is anything filtered?" question has an honest answer.
 * - The visit is `replace` + `preserveState` + `preserveScroll`. Without
 *   `replace`, typing pushes one history entry per keystroke; without
 *   `preserveState`, the Vue adapter re-keys the page on every visit and
 *   unmounts the input being typed into.
 */
export function useIndexTable<TFields extends Record<string, string>>(
    options: UseIndexTableOptions<TFields>,
): UseIndexTableReturn<TFields> {
    const { toUrl, sortState, fields, defaultSort, debounceMs = 300 } = options;

    const form = ref({ ...fields }) as Ref<TFields>;

    const isFiltered = computed(() =>
        Object.values(form.value).some((value) => value !== ''),
    ) as Ref<boolean>;

    function activeQuery(overrides: QueryParams = {}): QueryParams {
        const query: QueryParams = {};

        for (const [key, value] of Object.entries(form.value)) {
            if (value !== '') {
                query[key] = value;
            }
        }

        const { sort, direction } = sortState();

        if (
            sort !== defaultSort.column ||
            direction !== defaultSort.direction
        ) {
            query.sort = sort;
            query.direction = direction;
        }

        return { ...query, ...overrides };
    }

    let debounce: ReturnType<typeof setTimeout> | undefined;

    watch(
        form,
        () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                router.get(toUrl(activeQuery()), undefined, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            }, debounceMs);
        },
        { deep: true },
    );

    function hrefForPage(page: number): string {
        return toUrl(activeQuery({ page }));
    }

    /** Clicking the current sort column flips it; any other column starts ascending. */
    function sortHref(column: string): string {
        const { sort, direction } = sortState();
        const next = sort === column && direction === 'asc' ? 'desc' : 'asc';

        return toUrl(activeQuery({ sort: column, direction: next }));
    }

    function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
        const { sort, direction } = sortState();

        if (sort !== column) {
            return 'none';
        }

        return direction === 'asc' ? 'ascending' : 'descending';
    }

    /** Drop every filter. The watcher above turns this into one visit. */
    function clear(): void {
        const emptied = {} as Record<string, string>;

        for (const key of Object.keys(form.value)) {
            emptied[key] = '';
        }

        form.value = emptied as TFields;
    }

    return {
        form,
        isFiltered,
        activeQuery,
        hrefForPage,
        sortHref,
        ariaSort,
        clear,
    };
}
