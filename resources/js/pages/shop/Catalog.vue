<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import CatalogListing from '@/components/storefront/CatalogListing.vue';
import { catalog } from '@/routes';
import type { QueryParams } from '@/wayfinder';

/**
 * The whole-shop faceted listing.
 *
 * Everything here is the listing's own state, so the page is thin: it names the
 * URL the filters are written back to and hands the four server props straight
 * through to the shared listing.
 */
const { filters } = defineProps<{
    products: App.Data.ProductListData;
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
}>();

function hrefFor(query: QueryParams): string {
    return catalog.url({ query });
}

/** A search or a merchandising tag is what the shopper came for; say so. */
const heading = computed(() => {
    if (filters.q !== '') {
        return `Results for “${filters.q}”`;
    }

    if (filters.tag !== '') {
        return filters.tag;
    }

    if (filters.newArrivalsOnly) {
        return 'New arrivals';
    }

    return 'Shop all';
});
</script>

<template>
    <Head :title="heading" />

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <header>
            <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
            <h1
                class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
            >
                {{ heading }}
            </h1>
        </header>

        <div class="mt-8">
            <CatalogListing
                :products="products"
                :filters="filters"
                :category-facets="categoryFacets"
                :brand-facets="brandFacets"
                :href-for="hrefFor"
            />
        </div>
    </div>
</template>
