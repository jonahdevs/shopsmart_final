<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { catalog } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';

/**
 * An empty cart is a dead end, so it always offers two ways back into the
 * catalogue: the whole shop, and the aisles.
 *
 * The panel is the storefront's card — solid `--rule` hairline, `shadow-card`,
 * `--radius` corners — rather than the dashed outline `Empty` ships with, so a
 * dead end still reads as part of the same shelf as everything else.
 */
</script>

<template>
    <Empty
        class="border-rule shadow-card rounded-lg border border-solid bg-white"
    >
        <EmptyHeader>
            <EmptyMedia
                variant="icon"
                class="bg-tint text-electric size-12 rounded-lg"
            >
                <ShoppingCart aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-ink text-xl font-extrabold tracking-[-0.02em]"
            >
                Your cart is empty
            </EmptyTitle>
            <EmptyDescription>
                Nothing in here yet. Anything you add is kept for you, signed in
                or not.
            </EmptyDescription>
        </EmptyHeader>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <Link
                :href="catalog()"
                class="bg-electric font-display focus-visible:outline-electric rounded-lg px-5 py-2.5 text-sm font-bold text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Start shopping
            </Link>

            <Link
                :href="categoriesIndex()"
                class="border-rule font-display text-ink hover:border-electric hover:text-electric focus-visible:outline-electric rounded-lg border bg-white px-5 py-2.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Browse categories
            </Link>
        </div>
    </Empty>
</template>
