<script setup lang="ts">
import { BadgeCheck, Star } from '@lucide/vue';
import { computed } from 'vue';
import Rating from '@/components/storefront/Rating.vue';

/**
 * The approved reviews the server chose to send, with the store-wide summary
 * above them.
 *
 * Rating.vue is the summary here rather than the per-review mark: it always
 * states a review count next to its stars, which reads as noise on a row that
 * is one review by definition. Those rows draw their own five stars instead.
 */
const {
    reviews,
    average = null,
    count = 0,
} = defineProps<{
    reviews: App.Data.ReviewData[];
    average?: number | null;
    count?: number;
}>();

const averageLabel = computed(() =>
    average === null ? null : average.toFixed(1),
);
</script>

<template>
    <div class="flex flex-col gap-8">
        <div
            v-if="count > 0"
            class="flex flex-wrap items-center gap-x-6 gap-y-2"
        >
            <p class="flex items-baseline gap-1.5">
                <span
                    class="font-display text-foreground text-4xl font-extrabold tracking-[-0.02em] tabular-nums"
                >
                    {{ averageLabel ?? '—' }}
                </span>
                <span class="text-muted-foreground text-sm">out of 5</span>
            </p>

            <Rating :average="average" :count="count" />
        </div>

        <ul v-if="reviews.length" class="flex flex-col gap-8">
            <li
                v-for="review in reviews"
                :key="review.id"
                class="border-rule flex flex-col gap-2 border-b pb-8 last:border-b-0 last:pb-0"
            >
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <div
                        class="flex"
                        role="img"
                        :aria-label="`Rated ${review.rating} out of 5`"
                    >
                        <Star
                            v-for="star in 5"
                            :key="star"
                            class="size-3.5"
                            :class="
                                star <= review.rating
                                    ? 'fill-star text-star'
                                    : 'text-rule fill-transparent'
                            "
                            :stroke-width="1.5"
                            aria-hidden="true"
                        />
                    </div>

                    <p class="text-foreground text-sm font-medium">
                        {{ review.authorName }}
                    </p>

                    <p
                        v-if="review.verifiedPurchase"
                        class="text-muted-foreground flex items-center gap-1 text-xs"
                    >
                        <BadgeCheck class="size-3.5" aria-hidden="true" />
                        Verified purchase
                    </p>

                    <time
                        v-if="review.publishedAt"
                        :datetime="review.publishedAt"
                        class="text-muted-foreground text-xs"
                    >
                        {{ review.publishedAtForHumans }}
                    </time>
                </div>

                <h3
                    v-if="review.title"
                    class="text-foreground text-sm font-semibold"
                >
                    {{ review.title }}
                </h3>

                <p
                    class="text-muted-foreground max-w-3xl text-sm leading-6 whitespace-pre-line"
                >
                    {{ review.body }}
                </p>
            </li>
        </ul>

        <p v-else class="text-muted-foreground text-sm">
            No reviews yet. Be the first to say how this one worked out.
        </p>
    </div>
</template>
