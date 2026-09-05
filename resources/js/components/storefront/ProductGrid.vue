<script setup lang="ts">
import ProductCard from '@/components/storefront/ProductCard.vue';

/**
 * The listing grid.
 *
 * Column counts track ProductRail's slide fractions one step in, because the
 * grid gives up a sidebar's width that the full-bleed rail keeps — so a tile is
 * the same size on both, which is what makes the home page and the catalogue
 * read as one shop.
 */
const { products, eagerCount = 4 } = defineProps<{
    products: App.Data.ProductCardData[];
    /** How many tiles skip lazy loading; the first row is the page's LCP. */
    eagerCount?: number;
}>();
</script>

<template>
    <ul
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4 2xl:grid-cols-5"
    >
        <li v-for="(product, index) in products" :key="product.id">
            <ProductCard :product="product" :eager="index < eagerCount" />
        </li>
    </ul>
</template>
