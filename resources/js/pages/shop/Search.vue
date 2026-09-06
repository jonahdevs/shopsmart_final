<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Search as SearchIcon } from '@lucide/vue';
import { computed } from 'vue';
import CatalogListing from '@/components/storefront/CatalogListing.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { search } from '@/routes';
import type { QueryParams } from '@/wayfinder';

/**
 * The full search results page.
 *
 * The catalog listing with a term on it — same sidebar, sort, paging and empty
 * state — because a shopper who has just learned those controls on the catalog
 * should not have to learn a second, weaker set here.
 *
 * `searched` is the server's answer to "did this request run a search at all".
 * A shopper can land here from a bookmark with no `q`, or submit the header box
 * after one character, and neither is an error worth bouncing them for: the
 * page renders a prompt instead of a grid, and the header field stays exactly
 * where their cursor already is.
 */
const { filters, searched } = defineProps<{
    products: App.Data.ProductListData;
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
    searched: boolean;
    minimumTermLength: number;
}>();

function hrefFor(query: QueryParams): string {
    return search.url({ query });
}

const heading = computed(() =>
    searched ? `Results for “${filters.q}”` : 'Search the shop',
);

/** Distinguishes "you typed nothing" from "you typed one letter". */
const promptIsTooShort = computed(() => !searched && filters.q !== '');
</script>

<template>
    <Head :title="heading" />

    <div class="container py-8">
        <header>
            <p
                class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
            >
                Search
            </p>
            <h1
                class="font-display text-ink mt-1 text-2xl font-extrabold tracking-[-0.03em] sm:text-4xl"
            >
                {{ heading }}
            </h1>
            <p v-if="searched" class="text-muted-foreground mt-2 text-sm">
                Narrow these results with the filters, or search again from the
                box above.
            </p>
        </header>

        <div class="mt-8">
            <CatalogListing
                v-if="searched"
                :products="products"
                :filters="filters"
                :category-facets="categoryFacets"
                :brand-facets="brandFacets"
                :href-for="hrefFor"
                :search-term="filters.q"
            />

            <!--
              Not a listing with nothing in it: there is no result set to
              describe and no filter to loosen, so the page asks for a term
              rather than reporting zero of something.
            -->
            <Empty v-else class="border-rule shadow-card border bg-white">
                <EmptyHeader>
                    <EmptyMedia
                        variant="icon"
                        class="bg-tint text-electric rounded-lg"
                    >
                        <SearchIcon aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle
                        class="font-display text-ink text-xl font-extrabold tracking-[-0.02em]"
                    >
                        {{
                            promptIsTooShort
                                ? 'That is a little short'
                                : 'What are you looking for?'
                        }}
                    </EmptyTitle>
                    <EmptyDescription>
                        <template v-if="promptIsTooShort">
                            Search terms need at least
                            {{ minimumTermLength }} characters. Add a letter or
                            two and try again.
                        </template>
                        <template v-else>
                            Type a product, brand or model number into the
                            search box at the top of the page.
                        </template>
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </div>
    </div>
</template>
