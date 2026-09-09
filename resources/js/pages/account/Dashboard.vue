<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Heart,
    MapPin,
    Package,
    PenLine,
    Plus,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import NoOrders from '@/components/illustrations/NoOrders.vue';
import AccountPanel from '@/components/storefront/AccountPanel.vue';
import AccountStatTile from '@/components/storefront/AccountStatTile.vue';
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
import { formatIsoDate } from '@/lib/utils';
import { catalog } from '@/routes';
import {
    addresses as addressesRoute,
    recentlyViewed as recentlyViewedRoute,
    reviews as reviewsRoute,
} from '@/routes/account';
import { index as ordersIndex } from '@/routes/orders';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
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

/**
 * The signed-in user, off the shared props.
 *
 * `customerName` already arrives as its own prop, but the email and the join
 * date do not — and asking the controller for two more strings it does not
 * otherwise need would be a query budget spent on something every page in this
 * shell is already carrying.
 */
const page = usePage();

const user = computed(() => page.props.auth.user);

/**
 * The settings a shopper actually reaches for from a hub page. Appearance is
 * deliberately absent: dark mode is a staff affordance and the storefront is
 * always light, so offering it here would promise something that does nothing.
 */
const settingsLinks = [
    {
        label: 'Profile',
        description: 'Your name, email and phone number.',
        href: editProfile(),
        icon: UserRound,
    },
    {
        label: 'Security',
        description: 'Password, two-factor and passkeys.',
        href: editSecurity(),
        icon: ShieldCheck,
    },
];
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
                    <AccountStatTile
                        :label="tile.label"
                        :value="tile.count"
                        :href="tile.href"
                        :icon="tile.icon"
                    />
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
                    <!--
                      The same drawing as `account/Orders.vue`. A customer who
                      has never ordered meets this twice, and one picture in
                      both places says it is one absence rather than two.
                    -->
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

            <ul v-else class="border-rule mt-6 border-t">
                <OrderCard
                    v-for="order in recentOrders"
                    :key="order.id"
                    :order="order"
                />
            </ul>
        </section>

        <!--
          Two panels of facts rather than two more content sections: who you are
          and where things go are reference material, and the headed card is the
          device this area uses for that. SectionHeading stays for the ORDERS
          above, which is content a shopper reads through.
        -->
        <div class="grid gap-4 lg:grid-cols-2">
            <AccountPanel
                title="Account details"
                :icon="UserRound"
                :action-href="editProfile()"
                action-label="Edit"
            >
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt
                            class="text-muted-foreground text-[0.625rem] font-bold tracking-[0.16em] uppercase"
                        >
                            Name
                        </dt>
                        <dd class="text-ink mt-0.5 font-medium">
                            {{ customerName }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-muted-foreground text-[0.625rem] font-bold tracking-[0.16em] uppercase"
                        >
                            Email
                        </dt>
                        <dd class="text-ink mt-0.5 truncate font-medium">
                            {{ user.email }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-muted-foreground text-[0.625rem] font-bold tracking-[0.16em] uppercase"
                        >
                            Member since
                        </dt>
                        <dd class="text-ink mt-0.5 font-medium">
                            {{ formatIsoDate(user.created_at) }}
                        </dd>
                    </div>
                </dl>
            </AccountPanel>

            <AccountPanel
                title="Default address"
                :icon="MapPin"
                :action-href="addressesRoute()"
                action-label="Address book"
            >
                <div
                    v-if="defaultAddress"
                    class="flex items-start gap-3 text-sm"
                >
                    <MapPin
                        class="text-electric mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <p class="text-ink font-medium">
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
                    class="border-rule flex flex-col items-start gap-3 rounded-lg border border-dashed p-4"
                >
                    <p class="text-muted-foreground text-sm">
                        You have no saved addresses yet. Add one and checkout
                        will fill itself in.
                    </p>
                    <Link
                        :href="addressesRoute()"
                        class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Add an address
                    </Link>
                </div>
            </AccountPanel>
        </div>

        <!--
          Flush, because the rows are the panel's content and each one is its
          own hit target edge to edge. The focus ring is inset for the same
          reason AccountPanel documents: the panel clips its overflow.
        -->
        <AccountPanel title="Settings" :icon="ShieldCheck" flush>
            <ul>
                <li v-for="link in settingsLinks" :key="link.label">
                    <Link
                        :href="link.href"
                        class="border-rule hover:bg-tint focus-visible:outline-electric flex items-center gap-4 border-b px-5 py-3.5 transition-colors last:border-b-0 focus-visible:outline-2 focus-visible:-outline-offset-2"
                    >
                        <span
                            class="bg-tint-strong text-electric flex size-9 shrink-0 items-center justify-center rounded-lg"
                            aria-hidden="true"
                        >
                            <component :is="link.icon" class="size-4" />
                        </span>
                        <span class="min-w-0">
                            <span
                                class="font-display text-ink block text-sm font-bold"
                            >
                                {{ link.label }}
                            </span>
                            <span
                                class="text-muted-foreground block truncate text-xs"
                            >
                                {{ link.description }}
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </AccountPanel>

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
