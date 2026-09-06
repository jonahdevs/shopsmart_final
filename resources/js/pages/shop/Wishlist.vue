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
 *
 * The title keeps the section rhythm — blue eyebrow, heavy display line, muted
 * subtitle — as an `<h1>` rather than composing `SectionHeading`, which is an
 * `<h2>` and carries a link rather than a form submit.
 */
const { products } = defineProps<{
    products: App.Data.ProductCardData[];
}>();
</script>

<template>
    <Head title="Your wishlist" />

    <div class="container py-10">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div>
                <p
                    class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                >
                    Saved
                </p>
                <h1
                    class="font-display text-ink mt-0.5 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl"
                >
                    Your wishlist
                </h1>
                <p class="text-muted-foreground mt-1 text-sm tabular-nums">
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
                    class="border-rule text-muted-foreground hover:border-destructive hover:text-destructive focus-visible:outline-electric rounded-lg border bg-white px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                >
                    Clear the wishlist
                </button>
            </Form>
        </div>

        <SavedEmptyState
            v-if="products.length === 0"
            class="mt-8"
            list="wishlist"
        />

        <ul
            v-else
            class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
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
