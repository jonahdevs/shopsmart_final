<script setup lang="ts">
import { Star } from '@lucide/vue';

/**
 * One review's score, as five stars.
 *
 * Distinct from {@see Rating}, which is the product-wide summary and always
 * states a review count beside its stars — a count reads as noise on a row that
 * is one review by definition.
 *
 * Gold is `--star` and belongs to ratings alone.
 */
defineProps<{
    /** Whole stars, 1–5. */
    rating: number;
    size?: 'sm' | 'md';
}>();
</script>

<template>
    <div class="flex" role="img" :aria-label="`Rated ${rating} out of 5`">
        <Star
            v-for="star in 5"
            :key="star"
            :class="[
                size === 'md' ? 'size-4' : 'size-3.5',
                star <= rating
                    ? 'fill-star text-star'
                    : 'text-rule fill-transparent',
            ]"
            :stroke-width="1.5"
            aria-hidden="true"
        />
    </div>
</template>
