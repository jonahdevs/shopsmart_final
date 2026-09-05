<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import { useId } from 'vue';
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

const categoriesHeadingId = useId();
</script>

<template>
    <Head title="Everyday essentials, delivered across Kenya" />

    <h1 class="sr-only">ShopSmart</h1>

    <HeroCarousel :slides="heroSlides" />

    <!--
      The hero is the only full-bleed thing on the page, so it sits outside the
      measure and every section below puts `container` on itself instead.
    -->
    <div class="space-y-14 py-12">
        <section
            v-if="featuredCategories.length"
            class="container"
            :aria-labelledby="categoriesHeadingId"
        >
            <SectionHeading
                eyebrow="Explore"
                title="Shop by category"
                subtitle="Find exactly what you're looking for."
                :heading-id="categoriesHeadingId"
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
                <ProductRailSkeleton class="container" />
            </template>

            <ProductRail
                class="container"
                eyebrow="Just in"
                title="New arrivals"
                subtitle="Fresh products, just added to ShopSmart."
                :products="newArrivals ?? []"
                :view-all-href="catalog.url({ query: { arrivals: 1 } })"
            />
        </Deferred>

        <Deferred data="featuredProducts">
            <template #fallback>
                <ProductRailSkeleton class="container" />
            </template>

            <ProductRail
                class="container"
                eyebrow="Handpicked"
                title="Featured"
                subtitle="Quality products we think you'll love."
                :products="featuredProducts ?? []"
                :view-all-href="catalog.url({ query: { tag: 'Featured' } })"
            />
        </Deferred>
    </div>
</template>
