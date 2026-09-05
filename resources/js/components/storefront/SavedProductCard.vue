<script setup lang="ts">
import ProductCard from '@/components/storefront/ProductCard.vue';
import SavedRemoveButton from '@/components/storefront/SavedRemoveButton.vue';
import SavedToggleButton from '@/components/storefront/SavedToggleButton.vue';

/**
 * A wishlist tile: the catalogue's own card with the two actions a saved
 * product needs hanging underneath it.
 *
 * The actions sit below the card rather than floating over the artwork, so they
 * never cover the photography and never compete with the card's own link for a
 * tap.
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

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <SavedToggleButton :product-id="product.id" list="compare" />
            <SavedRemoveButton
                :product-id="product.id"
                :product-name="product.name"
                list="wishlist"
            />
        </div>
    </li>
</template>
