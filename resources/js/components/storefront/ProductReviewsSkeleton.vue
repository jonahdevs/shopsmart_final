<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Placeholder for the deferred review list. Mirrors ProductReviews' rhythm —
 * a summary line, then a meta row, a title and two lines of body per review —
 * so nothing below it moves when the real reviews land.
 */
defineProps<{ count?: number }>();
</script>

<template>
    <div aria-busy="true" aria-live="polite" aria-label="Loading reviews">
        <div aria-hidden="true" class="flex flex-col gap-8">
            <Skeleton class="h-10 w-40" />

            <div v-for="n in count ?? 3" :key="n" class="flex flex-col gap-2">
                <Skeleton class="h-4 w-64" />
                <Skeleton class="h-4 w-48" />
                <Skeleton class="h-4 w-full max-w-3xl" />
                <Skeleton class="h-4 w-2/3 max-w-3xl" />
            </div>
        </div>
    </div>
</template>
