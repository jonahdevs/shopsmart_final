<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import CategoryTiles from '@/components/storefront/CategoryTiles.vue';
import HeroCarousel from '@/components/storefront/HeroCarousel.vue';
import ProductRail from '@/components/storefront/ProductRail.vue';
import ProductRailSkeleton from '@/components/storefront/ProductRailSkeleton.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import { catalog } from '@/routes';

defineProps<{
    heroSlides: App.Data.HeroSlideData[];
    featuredCategories: App.Data.CategoryData[];
    newArrivals?: App.Data.ProductCardData[];
    featuredProducts?: App.Data.ProductCardData[];
}>();
</script>

<template>
    <Head title="Everyday essentials, delivered across Kenya" />

    <h1 class="sr-only">ShopSmart</h1>

    <HeroCarousel :slides="heroSlides" />

    <div class="space-y-16 px-4 py-12 sm:px-6 lg:px-8">
        <section v-if="featuredCategories.length">
            <SectionHeading
                title="Shop by category"
                :view-all-href="catalog()"
                view-all-label="Shop all"
            />
            <div class="mt-6">
                <CategoryTiles :categories="featuredCategories" />
            </div>
        </section>

        <!--
          Both rails are deferred so the hero paints on the first response; the
          skeletons hold the exact card geometry so nothing shifts when they land.
        -->
        <Deferred data="newArrivals">
            <template #fallback>
                <ProductRailSkeleton />
            </template>

            <ProductRail
                title="New arrivals"
                :products="newArrivals ?? []"
                :view-all-href="catalog.url({ query: { arrivals: 1 } })"
            />
        </Deferred>

        <Deferred data="featuredProducts">
            <template #fallback>
                <ProductRailSkeleton />
            </template>

            <ProductRail
                title="Featured"
                :products="featuredProducts ?? []"
                :view-all-href="catalog.url({ query: { tag: 'Featured' } })"
            />
        </Deferred>
    </div>
</template>
