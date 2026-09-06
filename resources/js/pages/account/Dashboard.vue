<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { Heart, MapPin, Package, PenLine, Plus } from '@lucide/vue';
import { computed } from 'vue';
import OrderCard from '@/components/storefront/OrderCard.vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import ProductGridSkeleton from '@/components/storefront/ProductGridSkeleton.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { catalog } from '@/routes';
import {
    addresses as addressesRoute,
    recentlyViewed as recentlyViewedRoute,
    reviews as reviewsRoute,
} from '@/routes/account';
import { index as ordersIndex } from '@/routes/orders';
import { index as wishlistIndex } from '@/routes/wishlist';

/**
 * The first screen after a customer signs in.
 *
 * Held to a fixed shape on purpose — four counts, three orders, one address and
 * one deferred rail — because every panel added here is another query on the
 * page a shopper sees most. Each count is a link rather than a statistic: the
 * number is only useful if pressing it takes you to the thing it counts.
 */
const { stats } = defineProps<{
    customerName: string;
    stats: App.Data.AccountStatsData;
    recentOrders: App.Data.OrderData[];
    defaultAddress: App.Data.AddressData | null;
    /** Deferred by the controller — undefined until the follow-up lands. */
    recentlyViewed?: App.Data.ProductCardData[];
    breadcrumbs: App.Data.BreadcrumbData[];
}>();

type AccountStat = {
    label: string;
    count: number;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

const tiles = computed<AccountStat[]>(() => [
    {
        label: stats.orderCount === 1 ? 'Order' : 'Orders',
        count: stats.orderCount,
        href: ordersIndex(),
        icon: Package,
    },
    {
        label: stats.addressCount === 1 ? 'Address' : 'Addresses',
        count: stats.addressCount,
        href: addressesRoute(),
        icon: MapPin,
    },
    {
        label: 'Saved',
        count: stats.wishlistCount,
        href: wishlistIndex(),
        icon: Heart,
    },
    {
        label: 'To review',
        count: stats.awaitingReviewCount,
        href: reviewsRoute(),
        icon: PenLine,
    },
]);
</script>

<template>
    <Head title="Your account" />

    <div class="flex flex-col gap-12">
        <p class="text-muted-foreground -mt-2 text-sm">
            Welcome back, {{ customerName }}. Everything you have bought, saved
            and written is here.
        </p>

        <section aria-labelledby="account-stats-heading">
            <h2 id="account-stats-heading" class="sr-only">At a glance</h2>

            <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <li v-for="tile in tiles" :key="tile.label">
                    <Link
                        :href="tile.href"
                        class="border-rule shadow-card hover:shadow-card-hover focus-visible:outline-electric flex h-full flex-col gap-3 rounded-lg border bg-white p-4 transition-shadow focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <span
                            class="bg-tint-strong text-electric flex size-9 items-center justify-center rounded-full"
                            aria-hidden="true"
                        >
                            <component :is="tile.icon" class="size-4" />
                        </span>
                        <span>
                            <span
                                class="font-display text-ink block text-2xl leading-none font-extrabold tracking-[-0.02em] tabular-nums"
                            >
                                {{ tile.count }}
                            </span>
                            <span
                                class="text-muted-foreground mt-1 block text-xs"
                            >
                                {{ tile.label }}
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </section>

        <section aria-labelledby="account-orders-heading">
            <SectionHeading
                eyebrow="Your history"
                title="Recent orders"
                subtitle="The last three you placed with us."
                heading-id="account-orders-heading"
                :view-all-href="ordersIndex()"
                view-all-label="All orders"
            />

            <Empty
                v-if="recentOrders.length === 0"
                class="border-rule mt-6 rounded-lg border"
            >
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Package aria-hidden="true" />
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

            <ul v-else class="border-rule mt-6 border-t">
                <OrderCard
                    v-for="order in recentOrders"
                    :key="order.id"
                    :order="order"
                />
            </ul>
        </section>

        <section aria-labelledby="account-address-heading">
            <SectionHeading
                eyebrow="Where it goes"
                title="Default address"
                subtitle="The one checkout reaches for first."
                heading-id="account-address-heading"
                :view-all-href="addressesRoute()"
                view-all-label="Address book"
            />

            <div
                v-if="defaultAddress"
                class="border-rule shadow-card mt-6 flex items-start gap-3 rounded-lg border bg-white p-5 text-sm"
            >
                <MapPin
                    class="text-electric mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p class="text-foreground font-medium">
                        {{ defaultAddress.fullName }}
                    </p>
                    <p class="text-muted-foreground mt-1 leading-relaxed">
                        {{ defaultAddress.summary }}
                    </p>
                    <p
                        v-if="defaultAddress.phone"
                        class="text-muted-foreground mt-1 tabular-nums"
                    >
                        {{ defaultAddress.phone }}
                    </p>
                </div>
            </div>

            <div
                v-else
                class="border-rule mt-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-dashed p-5"
            >
                <p class="text-muted-foreground text-sm">
                    You have no saved addresses yet. Add one and checkout will
                    fill itself in.
                </p>
                <Link
                    :href="addressesRoute()"
                    class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Add an address
                </Link>
            </div>
        </section>

        <!--
          Below the fold and nothing above it depends on it, so the page paints
          before this query runs. The skeleton holds the grid's exact geometry.
        -->
        <Deferred data="recentlyViewed">
            <template #fallback>
                <section aria-labelledby="account-viewed-heading">
                    <SectionHeading
                        eyebrow="Picking up"
                        title="Recently viewed"
                        subtitle="Where you left off last time."
                        heading-id="account-viewed-heading"
                    />
                    <ProductGridSkeleton class="mt-6" />
                </section>
            </template>

            <section
                v-if="recentlyViewed && recentlyViewed.length > 0"
                aria-labelledby="account-viewed-heading"
            >
                <SectionHeading
                    eyebrow="Picking up"
                    title="Recently viewed"
                    subtitle="Where you left off last time."
                    heading-id="account-viewed-heading"
                    :view-all-href="recentlyViewedRoute()"
                    view-all-label="See all"
                />

                <div class="mt-6">
                    <ProductGrid :products="recentlyViewed" />
                </div>
            </section>
        </Deferred>
    </div>
</template>
