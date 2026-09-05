<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { useId } from 'vue';
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
 * The arrows sit at the vertical midpoint of the cards and half off the first
 * and last one, which is what keeps them out of the copy underneath.
 */
defineProps<{
    title: string;
    products: App.Data.ProductCardData[];
    /** Blue micro-caps label above the title. */
    eyebrow?: string;
    /** One muted line under the title. */
    subtitle?: string;
    viewAllHref?: NonNullable<InertiaLinkProps['href']>;
}>();

const headingId = useId();
</script>

<template>
    <section v-if="products.length" :aria-labelledby="headingId">
        <SectionHeading
            :title="title"
            :eyebrow="eyebrow"
            :subtitle="subtitle"
            :heading-id="headingId"
            :view-all-href="viewAllHref"
        />

        <Carousel
            class="mt-6"
            :opts="{ align: 'start', containScroll: 'trimSnaps' }"
        >
            <CarouselContent class="-ml-3 items-stretch sm:-ml-4">
                <!--
                  Rails sit below the fold on every page that uses them, so no
                  card here is marked eager — `eager` is reserved for the first
                  grid row on Catalog and Category, which is the actual LCP.
                -->
                <CarouselItem
                    v-for="product in products"
                    :key="product.id"
                    class="basis-1/2 pl-3 sm:basis-1/3 sm:pl-4 lg:basis-1/4 xl:basis-1/5 2xl:basis-1/6"
                >
                    <ProductCard :product="product" />
                </CarouselItem>
            </CarouselContent>

            <!--
              Only from `lg`, where the page gutter is wide enough to hold a
              half-overhanging button without the body scrolling sideways.
              Below that the shelf is swiped.
            -->
            <CarouselPrevious
                class="border-rule text-ink shadow-card hover:bg-tint hover:text-electric top-[38%] -left-6 hidden size-10 bg-white lg:inline-flex"
            />
            <CarouselNext
                class="border-rule text-ink shadow-card hover:bg-tint hover:text-electric top-[38%] -right-6 hidden size-10 bg-white lg:inline-flex"
            />
        </Carousel>
    </section>
</template>
