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
 */
</script>

<template>
    <Empty class="border-rule border">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <ShoppingCart aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-lg font-black tracking-[-0.02em] uppercase"
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
                class="bg-electric font-display focus-visible:outline-electric rounded-xs px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Start shopping
            </Link>

            <Link
                :href="categoriesIndex()"
                class="font-display text-electric hover:border-electric focus-visible:outline-electric border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Browse categories
            </Link>
        </div>
    </Empty>
</template>
