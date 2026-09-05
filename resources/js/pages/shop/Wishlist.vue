<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SavedEmptyState from '@/components/storefront/SavedEmptyState.vue';
import SavedProductCard from '@/components/storefront/SavedProductCard.vue';
import { clear } from '@/routes/wishlist';

/**
 * The wishlist.
 *
 * The server sends products in saved order and has already dropped anything
 * that has since left the storefront, so this renders the list as given.
 */
const { products } = defineProps<{
    products: App.Data.ProductCardData[];
}>();
</script>

<template>
    <Head title="Your wishlist" />

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
                <h1
                    class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                >
                    Your wishlist
                </h1>
                <p class="text-muted-foreground mt-2 text-sm tabular-nums">
                    <template v-if="products.length === 0">
                        Nothing saved yet.
                    </template>
                    <template v-else>
                        {{ products.length }}
                        {{ products.length === 1 ? 'product' : 'products' }}
                        saved
                    </template>
                </p>
            </div>

            <Form
                v-if="products.length > 0"
                v-bind="clear.form()"
                v-slot="{ processing }"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    class="text-muted-foreground hover:text-destructive focus-visible:outline-electric rounded-xs text-sm underline underline-offset-4 transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 disabled:opacity-50"
                >
                    Clear the wishlist
                </button>
            </Form>
        </div>

        <SavedEmptyState
            v-if="products.length === 0"
            class="mt-10"
            list="wishlist"
        />

        <ul
            v-else
            class="mt-10 grid grid-cols-2 gap-x-4 gap-y-9 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
        >
            <SavedProductCard
                v-for="(product, index) in products"
                :key="product.id"
                :product="product"
                :eager="index < 4"
            />
        </ul>
    </div>
</template>
