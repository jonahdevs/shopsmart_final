<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import ActiveFilters from '@/components/storefront/ActiveFilters.vue';
import CatalogEmptyState from '@/components/storefront/CatalogEmptyState.vue';
import CatalogFacets from '@/components/storefront/CatalogFacets.vue';
import CatalogToolbar from '@/components/storefront/CatalogToolbar.vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import {
    catalogQuery,
    hasPriceBound,
    type CatalogFilterPatch,
} from '@/components/storefront/catalogFilters';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { QueryParams } from '@/wayfinder';

/**
 * The faceted listing shared by the catalog and every category page.
 *
 * Both pages publish the same four props and differ only in which URL the
 * filters are written back to, so the whole mechanism — the sidebar, the sort,
 * the summary, the paging — lives here once and is handed a `hrefFor` for its
 * own route.
 *
 * Navigation model, matching what the controllers actually return:
 *
 * - `products` is an Inertia merge prop appending into `data`, so the next page
 *   is a partial reload that adds tiles instead of replacing the grid.
 * - A filter or sort change is the same partial reload with `reset: ['products']`
 *   and no `page`, which drops the accumulated tiles and starts again at one.
 * - Every visit is built from the server's echo of the filter state, so the
 *   filters a shopper has on are carried into the next page and into every
 *   other facet click without being re-parsed out of the URL.
 */
const { products, filters, categoryFacets, brandFacets, hrefFor } =
    defineProps<{
        products: App.Data.ProductListData;
        filters: App.Data.CatalogFilterData;
        categoryFacets: App.Data.FacetOptionData[];
        brandFacets: App.Data.FacetOptionData[];
        /** Builds this listing's own URL for a query — catalog or category. */
        hrefFor: (query: QueryParams) => string;
        /** Sidebar heading for the category facet group. */
        categoryLabel?: string;
        /**
         * The term this listing is a search for, when it is one. It only
         * changes what the empty state says: "nothing matched *this*" is a
         * different dead end from "this shelf is bare", and the shopper's next
         * move differs accordingly.
         */
        searchTerm?: string;
    }>();

/** Everything a filter change can alter; `category` and the trail cannot. */
const LISTING_PROPS = ['products', 'filters', 'categoryFacets', 'brandFacets'];

const filtersOpen = ref(false);
const loadingMore = ref(false);
const endOfResults = ref<HTMLElement | null>(null);

const activeFilterCount = computed(
    () =>
        filters.categories.length +
        filters.brands.length +
        (filters.q === '' ? 0 : 1) +
        (hasPriceBound(filters) ? 1 : 0) +
        (filters.minRating > 0 ? 1 : 0) +
        (filters.inStockOnly ? 1 : 0) +
        (filters.tag === '' ? 0 : 1) +
        (filters.newArrivalsOnly ? 1 : 0),
);

/**
 * Apply a change to the filter state.
 *
 * `reset` clears the merge prop so the grid is replaced rather than appended
 * to, and dropping `page` sends the listing back to the first page — a filter
 * that narrows the results has no page four to stay on.
 */
function applyPatch(patch: CatalogFilterPatch): void {
    router.get(
        hrefFor(catalogQuery(filters, patch)),
        {},
        {
            only: LISTING_PROPS,
            reset: ['products'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function changeSort(sort: string): void {
    if (sort !== filters.sort) {
        applyPatch({ sort });
    }
}

/**
 * Fetch the next page into the same grid. No `reset`, so the merge prop
 * appends; `replace` because ten pages of a listing are one place, not ten
 * entries in the shopper's history.
 */
function loadMore(): void {
    if (loadingMore.value || !products.hasMorePages) {
        return;
    }

    loadingMore.value = true;

    router.get(
        hrefFor(catalogQuery(filters, {}, products.currentPage + 1)),
        {},
        {
            only: ['products'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                loadingMore.value = false;

                // The button is what had focus, and it disappears on the last
                // page. Without this, focus falls to the document body and a
                // keyboard shopper restarts from the top of the page.
                if (!products.hasMorePages) {
                    void nextTick(() => endOfResults.value?.focus());
                }
            },
        },
    );
}
</script>

<template>
    <div>
        <CatalogToolbar
            :shown="products.data.length"
            :total="products.total"
            :sort="filters.sort"
            :active-filter-count="activeFilterCount"
            @sort="changeSort"
            @open-filters="filtersOpen = true"
        />

        <div class="mt-4">
            <ActiveFilters
                :filters="filters"
                :category-facets="categoryFacets"
                :brand-facets="brandFacets"
                @update="applyPatch"
            />
        </div>

        <div class="mt-8 flex items-start gap-8">
            <aside
                class="border-rule shadow-card hidden w-56 shrink-0 rounded-lg border bg-white p-4 lg:block xl:w-64"
            >
                <h2
                    class="font-display text-ink border-rule mb-4 border-b pb-3 text-base font-extrabold tracking-[-0.02em]"
                >
                    Filter
                </h2>
                <CatalogFacets
                    :filters="filters"
                    :category-facets="categoryFacets"
                    :brand-facets="brandFacets"
                    :category-label="categoryLabel"
                    @update="applyPatch"
                />
            </aside>

            <!-- `min-w-0` so a wide tile cannot push the page past 320px. -->
            <div class="min-w-0 flex-1">
                <ProductGrid
                    v-if="products.data.length > 0"
                    :products="products.data"
                />

                <CatalogEmptyState
                    v-else
                    :has-active-filters="filters.hasActiveFilters"
                    :search-term="searchTerm"
                    @update="applyPatch"
                />

                <div
                    v-if="products.data.length > 0"
                    class="mt-12 flex flex-col items-center gap-3"
                >
                    <button
                        v-if="products.hasMorePages"
                        type="button"
                        class="border-rule shadow-card hover:shadow-card-hover hover:border-electric hover:text-electric focus-visible:outline-electric font-display text-ink inline-flex items-center gap-2 rounded-lg border bg-white px-6 py-2.5 text-sm font-bold transition-all focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60"
                        :disabled="loadingMore"
                        @click="loadMore"
                    >
                        <Loader2
                            v-if="loadingMore"
                            class="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        {{ loadingMore ? 'Loading' : 'Load more products' }}
                    </button>

                    <p
                        v-else
                        ref="endOfResults"
                        tabindex="-1"
                        class="text-muted-foreground focus-visible:outline-electric text-sm focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        You have reached the end of the results.
                    </p>
                </div>
            </div>
        </div>

        <!--
          Below `lg` the sidebar is a sheet. It stays open while facets are
          ticked so several can be changed in one go, with the footer reporting
          what the grid behind it now holds.
        -->
        <!--
          `storefront` is restated here because the sheet is portalled to the
          body, outside StoreShell's wrapper: without it the panel resolves the
          staff palette instead of the brand one.
        -->
        <Sheet v-model:open="filtersOpen">
            <SheetContent
                side="left"
                class="storefront w-11/12 gap-0 sm:max-w-sm"
            >
                <SheetHeader class="border-rule border-b">
                    <SheetTitle
                        class="font-display text-ink text-lg font-extrabold tracking-[-0.02em]"
                    >
                        Filter
                    </SheetTitle>
                    <SheetDescription>
                        Narrow the listing. The grid updates as you go.
                    </SheetDescription>
                </SheetHeader>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <CatalogFacets
                        :filters="filters"
                        :category-facets="categoryFacets"
                        :brand-facets="brandFacets"
                        :category-label="categoryLabel"
                        @update="applyPatch"
                    />
                </div>

                <div class="border-rule border-t p-4">
                    <button
                        type="button"
                        class="bg-electric focus-visible:outline-electric w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                        @click="filtersOpen = false"
                    >
                        Show {{ products.total }}
                        {{ products.total === 1 ? 'product' : 'products' }}
                    </button>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
