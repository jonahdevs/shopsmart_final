<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Package } from '@lucide/vue';
import { computed } from 'vue';
import OrderCard from '@/components/storefront/OrderCard.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
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

    <div class="container flex flex-col gap-16 py-8">
        <section aria-labelledby="orders-heading">
            <StoreBreadcrumbs :items="breadcrumbs" />

            <div class="mt-6">
                <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
                <h1
                    id="orders-heading"
                    class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                >
                    Your orders
                </h1>
                <p class="text-muted-foreground mt-2 text-sm">
                    Everything you have placed with us, newest first.
                </p>
            </div>

            <Empty v-if="orders.length === 0" class="border-rule mt-10 border">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Package aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle
                        class="font-display text-lg font-black tracking-[-0.02em] uppercase"
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
                    class="bg-electric font-display focus-visible:outline-electric rounded-xs px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    Start shopping
                </Link>
            </Empty>

            <template v-else>
                <ul class="border-rule mt-10 border-t">
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
                        class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-xs border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Newer orders
                    </Link>
                    <span v-else />

                    <Link
                        v-if="hasMore"
                        :href="index.url({ query: { page: currentPage + 1 } })"
                        class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-xs border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        Older orders
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </nav>
            </template>
        </section>
    </div>
</template>
