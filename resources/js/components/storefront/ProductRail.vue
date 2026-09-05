<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import ProductCard from '@/components/storefront/ProductCard.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';

/**
 * A horizontally scrolling shelf of products.
 *
 * Slide widths are fractional so the next card always peeks past the edge —
 * the affordance that tells you the shelf keeps going without needing a hint.
 */
defineProps<{
    title: string;
    products: App.Data.ProductCardData[];
    viewAllHref?: NonNullable<InertiaLinkProps['href']>;
}>();
</script>

<template>
    <section v-if="products.length">
        <SectionHeading :title="title" :view-all-href="viewAllHref" />

        <Carousel
            class="mt-6"
            :opts="{ align: 'start', containScroll: 'trimSnaps' }"
        >
            <CarouselContent class="-ml-4">
                <!--
                  Rails sit below the fold on every page that uses them, so no
                  card here is marked eager — `eager` is reserved for the first
                  grid row on Catalog and Category, which is the actual LCP.
                -->
                <CarouselItem
                    v-for="product in products"
                    :key="product.id"
                    class="basis-1/2 pl-4 sm:basis-1/3 lg:basis-1/4 xl:basis-1/5 2xl:basis-1/6"
                >
                    <ProductCard :product="product" />
                </CarouselItem>
            </CarouselContent>

            <CarouselPrevious class="-left-4 hidden lg:inline-flex" />
            <CarouselNext class="-right-4 hidden lg:inline-flex" />
        </Carousel>
    </section>
</template>
