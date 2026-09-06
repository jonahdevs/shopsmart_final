<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import AccountReviewCard from '@/components/storefront/AccountReviewCard.vue';
import ProductGridSkeleton from '@/components/storefront/ProductGridSkeleton.vue';
import ReviewInviteCard from '@/components/storefront/ReviewInviteCard.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index as ordersIndex } from '@/routes/orders';

/**
 * Everything the shopper has written, and everything they could still write
 * about.
 *
 * Two halves on purpose: the reviews already submitted answer "did mine go
 * through", and the invitations below answer "what else can I say something
 * about" — which is the question that actually produces reviews.
 */
defineProps<{
    reviews: App.Data.AccountReviewData[];
    /** Deferred by the controller — undefined until the follow-up lands. */
    awaitingReview?: App.Data.ProductCardData[];
    breadcrumbs: App.Data.BreadcrumbData[];
}>();
</script>

<template>
    <Head title="Your reviews" />

    <div class="flex flex-col gap-12">
        <section aria-labelledby="account-written-heading">
            <SectionHeading
                eyebrow="In your words"
                title="Reviews you have written"
                subtitle="Newest first. A review stays private until our team publishes it."
                heading-id="account-written-heading"
            />

            <Empty
                v-if="reviews.length === 0"
                class="border-rule mt-6 rounded-lg border"
            >
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Star aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle
                        class="font-display text-lg font-extrabold tracking-[-0.02em]"
                    >
                        You have not written a review yet
                    </EmptyTitle>
                    <EmptyDescription>
                        Once an order has been delivered you can say how it
                        worked out, and the next shopper gets to read it.
                    </EmptyDescription>
                </EmptyHeader>

                <Link
                    :href="ordersIndex()"
                    class="bg-electric font-display focus-visible:outline-electric rounded-lg px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    See your orders
                </Link>
            </Empty>

            <ul v-else class="mt-6 flex flex-col gap-4">
                <AccountReviewCard
                    v-for="review in reviews"
                    :key="review.id"
                    :review="review"
                />
            </ul>
        </section>

        <!--
          Two extra queries behind a list the page can already render, so the
          controller defers them and the skeleton holds the grid's geometry.
        -->
        <Deferred data="awaitingReview">
            <template #fallback>
                <section aria-labelledby="account-awaiting-heading">
                    <SectionHeading
                        eyebrow="Over to you"
                        title="Waiting on your verdict"
                        subtitle="Things you have received and not written about yet."
                        heading-id="account-awaiting-heading"
                    />
                    <ProductGridSkeleton class="mt-6" />
                </section>
            </template>

            <section
                v-if="awaitingReview && awaitingReview.length > 0"
                aria-labelledby="account-awaiting-heading"
            >
                <SectionHeading
                    eyebrow="Over to you"
                    title="Waiting on your verdict"
                    subtitle="Things you have received and not written about yet."
                    heading-id="account-awaiting-heading"
                />

                <ul
                    class="mt-6 grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 sm:gap-x-4 xl:grid-cols-4 2xl:grid-cols-5"
                >
                    <ReviewInviteCard
                        v-for="(product, index) in awaitingReview"
                        :key="product.id"
                        :product="product"
                        :eager="index < 4"
                    />
                </ul>
            </section>
        </Deferred>
    </div>
</template>
