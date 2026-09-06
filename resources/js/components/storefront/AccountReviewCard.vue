<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck } from '@lucide/vue';
import { computed } from 'vue';
import OrderStatusBadge from '@/components/storefront/OrderStatusBadge.vue';
import ReviewStars from '@/components/storefront/ReviewStars.vue';
import { show } from '@/routes/product';

/**
 * A review the shopper wrote, on the product it is about.
 *
 * The moderation state is stated plainly rather than hidden: a review sitting
 * in the queue is not a review that failed, and a shopper who cannot see the
 * difference assumes the second one.
 *
 * Both the wording and the badge variant come off the enum on the server, so
 * nothing here maps a status to a colour.
 */
const { review } = defineProps<{ review: App.Data.AccountReviewData }>();

const isPending = computed(() => review.status === 'pending');
</script>

<template>
    <li
        class="border-rule shadow-card flex flex-col gap-4 rounded-lg border bg-white p-5 sm:flex-row sm:gap-5"
    >
        <!--
          Decoration beside a title that already names the product, so it
          carries no link of its own and no alternative text.
        -->
        <div
            class="size-16 shrink-0 overflow-hidden rounded-lg bg-white"
            aria-hidden="true"
        >
            <img
                v-if="review.product.image"
                :src="review.product.image.thumbUrl ?? review.product.image.url"
                :alt="review.product.image.alt"
                loading="lazy"
                decoding="async"
                class="size-full object-contain"
            />
            <div v-else class="bg-muted size-full rounded-lg" />
        </div>

        <div class="min-w-0 flex-1">
            <div
                class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2"
            >
                <h3
                    class="text-foreground min-w-0 text-sm leading-5 font-medium"
                >
                    <Link
                        :href="show(review.product.slug)"
                        class="hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        {{ review.product.name }}
                    </Link>
                </h3>

                <OrderStatusBadge
                    :label="review.statusLabel"
                    :variant="review.statusVariant"
                />
            </div>

            <div
                class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
            >
                <ReviewStars :rating="review.rating" />

                <p
                    v-if="review.verifiedPurchase"
                    class="text-muted-foreground flex items-center gap-1"
                >
                    <BadgeCheck class="size-3.5" aria-hidden="true" />
                    Verified purchase
                </p>

                <time
                    v-if="review.submittedAt"
                    :datetime="review.submittedAt"
                    class="text-muted-foreground"
                >
                    {{ review.submittedAtForHumans }}
                </time>
            </div>

            <h4
                v-if="review.title"
                class="text-foreground mt-3 text-sm font-semibold"
            >
                {{ review.title }}
            </h4>

            <p
                class="text-muted-foreground mt-1 text-sm leading-6 whitespace-pre-line"
            >
                {{ review.body }}
            </p>

            <p v-if="isPending" class="text-muted-foreground mt-3 text-xs">
                Waiting on our team to publish it. Nobody else can see it yet.
            </p>
        </div>
    </li>
</template>
