<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { catalog } from '@/routes';

/**
 * What the shopper has been looking at, most recent first.
 *
 * Not deferred here, unlike the rail on the dashboard: this page is the query,
 * so there is nothing above it that a deferred load would let paint sooner.
 */
defineProps<{
    products: App.Data.ProductCardData[];
    breadcrumbs: App.Data.BreadcrumbData[];
}>();
</script>

<template>
    <Head title="Recently viewed" />

    <section
        aria-labelledby="recently-viewed-heading"
        class="flex flex-col gap-6"
    >
        <h2 id="recently-viewed-heading" class="sr-only">Recently viewed</h2>

        <p class="text-muted-foreground -mt-2 text-sm tabular-nums">
            <template v-if="products.length === 0">
                Nothing here yet.
            </template>
            <template v-else>
                The last {{ products.length }}
                {{ products.length === 1 ? 'product' : 'products' }} you opened,
                newest first.
            </template>
        </p>

        <Empty
            v-if="products.length === 0"
            class="border-rule rounded-lg border"
        >
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Eye aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle
                    class="font-display text-lg font-extrabold tracking-[-0.02em]"
                >
                    Nothing viewed yet
                </EmptyTitle>
                <EmptyDescription>
                    Open a product and it lands here, so you can pick up where
                    you left off.
                </EmptyDescription>
            </EmptyHeader>

            <Link
                :href="catalog()"
                class="bg-electric font-display focus-visible:outline-electric rounded-lg px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Browse the shop
            </Link>
        </Empty>

        <ProductGrid v-else :products="products" />
    </section>
</template>
