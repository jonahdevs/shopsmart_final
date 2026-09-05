<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Placeholder for a deferred rail. Mirrors ProductCard's proportions exactly —
 * square image, two title lines, a rating row, a price block — so the layout
 * does not shift when the real products arrive.
 *
 * The column count has to track ProductRail's slide fractions at every
 * breakpoint: the cards are `aspect-square`, so a column count is really a
 * height, and disagreeing at one breakpoint reintroduces the shift this is
 * here to absorb. Six is the widest row, so that is the default fill.
 */
defineProps<{ count?: number }>();
</script>

<template>
    <section aria-busy="true" aria-live="polite" aria-label="Loading products">
        <div aria-hidden="true">
            <Skeleton class="h-0.5 w-8 rounded-none" />
            <Skeleton class="mt-3 h-7 w-56" />
        </div>

        <div
            aria-hidden="true"
            class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
        >
            <div v-for="n in count ?? 6" :key="n" class="space-y-3">
                <Skeleton class="aspect-square w-full" />
                <Skeleton class="h-3 w-16" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="h-7 w-24" />
            </div>
        </div>
    </section>
</template>
