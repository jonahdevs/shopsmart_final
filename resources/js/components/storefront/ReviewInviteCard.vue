<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PenLine } from '@lucide/vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import { create } from '@/routes/account/reviews';

/**
 * Something the shopper received and has not written about yet.
 *
 * The catalogue's own card with the one action this context adds hanging
 * underneath it, the way the wishlist tile does — below the artwork rather than
 * over it, so it never covers the photography or competes with the card's own
 * link for a tap.
 */
defineProps<{
    product: App.Data.ProductCardData;
    /** Set on the first row so above-the-fold art is not lazy-loaded. */
    eager?: boolean;
}>();
</script>

<template>
    <li class="flex flex-col gap-3">
        <ProductCard :product="product" :eager="eager" :savable="false" />

        <Link
            :href="create(product.slug)"
            class="font-display text-electric hover:border-electric focus-visible:outline-electric inline-flex w-fit items-center gap-1.5 border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
        >
            <PenLine class="size-4" aria-hidden="true" />
            Write a review
            <span class="sr-only">of {{ product.name }}</span>
        </Link>
    </li>
</template>
