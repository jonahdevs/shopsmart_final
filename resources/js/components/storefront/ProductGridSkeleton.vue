<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Placeholder for a deferred {@see ProductGrid}.
 *
 * Mirrors the grid's column counts and ProductCard's proportions exactly — a
 * bordered card, a square image well, a brand line, two title lines, a rating
 * row and a price block — so nothing shifts when the real products land.
 */
defineProps<{ count?: number }>();
</script>

<template>
    <div
        aria-busy="true"
        aria-live="polite"
        aria-label="Loading products"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4 2xl:grid-cols-5"
    >
        <div
            v-for="n in count ?? 4"
            :key="n"
            aria-hidden="true"
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
</template>
