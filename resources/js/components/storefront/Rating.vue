<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Star rating. Height is reserved even at zero reviews so price baselines stay
 * aligned across a grid of cards, some rated and some not.
 *
 * Gold is `--star` and belongs to this component alone: spending it as a
 * general accent would cost a shopper the one signal they scan a listing for.
 */
const { average = null, count = 0 } = defineProps<{
    average?: number | null;
    count?: number;
}>();

const rounded = computed(() => Math.round(average ?? 0));

/**
 * The stars are decorative, so the whole cluster carries the name — which needs
 * a `role` that permits one. A product can hold reviews with no scored average,
 * so the score is only stated when there is one.
 */
const label = computed(() => {
    const reviews = `${count} ${count === 1 ? 'review' : 'reviews'}`;

    return average === null || average === undefined
        ? reviews
        : `Rated ${average.toFixed(1)} out of 5 from ${reviews}`;
});
</script>

<template>
    <div
        v-if="count > 0"
        class="flex items-center gap-1.5"
        role="img"
        :aria-label="label"
    >
        <div class="flex gap-px" aria-hidden="true">
            <Star
                v-for="star in 5"
                :key="star"
                class="size-3.5"
                :class="
                    star <= rounded
                        ? 'fill-star text-star'
                        : 'text-rule fill-transparent'
                "
                :stroke-width="1.5"
            />
        </div>
        <span class="text-muted-foreground text-xs tabular-nums">
            ({{ count }})
        </span>
    </div>

    <!--
      Placeholder keeps the card's vertical rhythm identical to a rated one:
      the rated row is as tall as the 16px line box of the review count, not as
      tall as the 14px stars sitting inside it.
    -->
    <div v-else class="h-4" aria-hidden="true" />
</template>
