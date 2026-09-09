<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import NoOrders from '@/components/illustrations/NoOrders.vue';
import OrderCard from '@/components/storefront/OrderCard.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { catalog } from '@/routes';
import { index } from '@/routes/orders';

/**
 * The order history.
 *
 * Paged rather than infinitely scrolled: an order history is something people
 * come back to looking for one specific order, and a page they can link to and
 * go back through beats a list that has to be re-grown every visit.
 *
 * The trail and the H1 come from AccountLayout, off the `breadcrumbs` prop, so
 * this page renders neither.
 */
defineProps<{
    orders: App.Data.OrderData[];
    hasMore: boolean;
    breadcrumbs: App.Data.BreadcrumbData[];
}>();

const page = usePage();

/**
 * Which page of history is on screen. Read off the URL because the server sends
 * only "is there another one" — there is no page number in the props to trust.
 */
const currentPage = computed(() => {
    const query = new URLSearchParams(page.url.split('?')[1] ?? '');
    const value = Number(query.get('page') ?? 1);

    return Number.isFinite(value) && value > 1 ? Math.floor(value) : 1;
});
</script>

<template>
    <Head title="Your orders" />

    <div class="flex flex-col gap-8">
        <p class="text-muted-foreground -mt-2 text-sm">
            Everything you have placed with us, newest first.
        </p>

        <section aria-labelledby="order-history-heading">
            <h2 id="order-history-heading" class="sr-only">Order history</h2>

            <Empty
                v-if="orders.length === 0"
                class="border-rule rounded-lg border"
            >
                <EmptyHeader>
                    <!-- Shared with the dashboard's empty history; see there. -->
                    <EmptyMedia variant="default" class="w-[180px]">
                        <NoOrders />
                    </EmptyMedia>
                    <EmptyTitle
                        class="font-display text-lg font-extrabold tracking-[-0.02em]"
                    >
                        No orders yet
                    </EmptyTitle>
                    <EmptyDescription>
                        When you place an order it lands here, with everything
                        you bought and what it cost.
                    </EmptyDescription>
                </EmptyHeader>

                <Link
                    :href="catalog()"
                    class="bg-electric font-display focus-visible:outline-electric rounded-lg px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    Start shopping
                </Link>
            </Empty>

            <template v-else>
                <ul class="border-rule border-t">
                    <OrderCard
                        v-for="order in orders"
                        :key="order.id"
                        :order="order"
                    />
                </ul>

                <nav
                    v-if="hasMore || currentPage > 1"
                    aria-label="Order history pages"
                    class="mt-10 flex items-center justify-between gap-4"
                >
                    <Link
                        v-if="currentPage > 1"
                        :href="index.url({ query: { page: currentPage - 1 } })"
                        class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Newer orders
                    </Link>
                    <span v-else />

                    <Link
                        v-if="hasMore"
                        :href="index.url({ query: { page: currentPage + 1 } })"
                        class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        Older orders
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </nav>
            </template>
        </section>
    </div>
</template>
