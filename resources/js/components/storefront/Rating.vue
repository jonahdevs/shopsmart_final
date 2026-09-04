<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Star rating. Height is reserved even at zero reviews so price baselines stay
 * aligned across a grid of cards, some rated and some not.
 */
const { average = null, count = 0 } = defineProps<{
    average?: number | null;
    count?: number;
}>();

const rounded = computed(() => Math.round(average ?? 0));
</script>

<template>
    <div
        v-if="count > 0"
        class="flex items-center gap-1.5"
        :aria-label="`Rated ${average?.toFixed(1)} out of 5 from ${count} reviews`"
    >
        <div class="flex" aria-hidden="true">
            <Star
                v-for="star in 5"
                :key="star"
                class="size-3.5"
                :class="
                    star <= rounded
                        ? 'fill-amber-400 text-amber-400'
                        : 'fill-transparent text-rule'
                "
                :stroke-width="1.5"
            />
        </div>
        <span class="text-xs text-muted-foreground tabular-nums">{{ count }}</span>
    </div>

    <!-- Placeholder keeps the card's vertical rhythm identical to a rated one. -->
    <div v-else class="h-3.5" aria-hidden="true" />
</template>
