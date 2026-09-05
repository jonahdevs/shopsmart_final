<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CatalogListing from '@/components/storefront/CatalogListing.vue';
import CategoryCardList from '@/components/storefront/CategoryCardList.vue';
import CategoryHeader from '@/components/storefront/CategoryHeader.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { show } from '@/routes/category';
import type { QueryParams } from '@/wayfinder';

/**
 * One category's listing.
 *
 * The same engine as the catalog, pinned to a subtree: the only differences are
 * the name board above it, the child tiles, and the fact that the category
 * facets here are this category's children rather than the whole taxonomy —
 * which is why the sidebar group is labelled as sub-categories.
 */
const { category } = defineProps<{
    category: App.Data.CategoryData;
    breadcrumbs: App.Data.BreadcrumbData[];
    products: App.Data.ProductListData;
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
}>();

function hrefFor(query: QueryParams): string {
    return show.url(category.slug, { query });
}
</script>

<template>
    <Head :title="category.name" />

    <div class="px-4 py-6 sm:px-6 lg:px-8">
        <StoreBreadcrumbs :items="breadcrumbs" />

        <div class="mt-6">
            <CategoryHeader :category="category" />
        </div>

        <section v-if="category.children.length > 0" class="mt-10">
            <SectionHeading title="Shop by sub-category" />
            <div class="mt-5">
                <CategoryCardList :categories="category.children" />
            </div>
        </section>

        <div class="mt-10">
            <CatalogListing
                :products="products"
                :filters="filters"
                :category-facets="categoryFacets"
                :brand-facets="brandFacets"
                :href-for="hrefFor"
                category-label="Sub-category"
            />
        </div>
    </div>
</template>
