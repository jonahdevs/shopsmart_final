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
                <CarouselItem
                    v-for="(product, index) in products"
                    :key="product.id"
                    class="basis-1/2 pl-4 sm:basis-1/3 lg:basis-1/4 xl:basis-1/5 2xl:basis-[16.6667%]"
                >
                    <ProductCard :product="product" :eager="index < 5" />
                </CarouselItem>
            </CarouselContent>

            <CarouselPrevious class="-left-4 hidden lg:inline-flex" />
            <CarouselNext class="-right-4 hidden lg:inline-flex" />
        </Carousel>
    </section>
</template>
