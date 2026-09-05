<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Placeholder for a deferred rail. Mirrors ProductCard's proportions exactly —
 * a bordered card, a square image well, a brand line, two title lines, a rating
 * row and a price block — so the layout does not shift when the real products
 * arrive.
 *
 * The column count and the gutter have to track ProductRail's slide fractions
 * at every breakpoint: the cards are `aspect-square`, so a column count is
 * really a height, and disagreeing at one breakpoint reintroduces the shift
 * this is here to absorb. Six is the widest row, so that is the default fill.
 */
defineProps<{ count?: number }>();
</script>

<template>
    <section aria-busy="true" aria-live="polite" aria-label="Loading products">
        <div aria-hidden="true">
            <Skeleton class="h-2.5 w-16" />
            <Skeleton class="mt-2 h-7 w-56" />
            <Skeleton class="mt-2 h-4 w-64" />
        </div>

        <div
            aria-hidden="true"
            class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
        >
            <div
                v-for="n in count ?? 6"
                :key="n"
                class="border-rule shadow-card rounded-lg border bg-white"
            >
                <div class="aspect-square p-4 sm:p-5">
                    <Skeleton class="size-full rounded-md" />
                </div>
                <div class="space-y-2 px-4 pt-1 pb-4">
                    <Skeleton class="h-3 w-16" />
                    <Skeleton class="h-4 w-full" />
                    <Skeleton class="h-4 w-2/3" />
                    <Skeleton class="h-4 w-20" />
                    <Skeleton class="h-6 w-24" />
                </div>
            </div>
        </div>
    </section>
</template>
