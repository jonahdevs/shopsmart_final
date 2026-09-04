<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Placeholder for a deferred rail. Mirrors ProductCard's proportions exactly —
 * square image, two title lines, a rating row, a price block — so the layout
 * does not shift when the real products arrive.
 */
defineProps<{ count?: number }>();
</script>

<template>
    <section aria-hidden="true">
        <div>
            <Skeleton class="h-0.5 w-8 rounded-none" />
            <Skeleton class="mt-3 h-7 w-56" />
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div v-for="n in count ?? 5" :key="n" class="space-y-3">
                <Skeleton class="aspect-square w-full" />
                <Skeleton class="h-3 w-16" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="h-7 w-24" />
            </div>
        </div>
    </section>
</template>
