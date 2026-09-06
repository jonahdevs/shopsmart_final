<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Eye,
    House,
    MapPin,
    Package,
    ShieldCheck,
    Star,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import {
    addresses as addressesRoute,
    dashboard,
    recentlyViewed,
    reviews,
} from '@/routes/account';
import { index as ordersIndex } from '@/routes/orders';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

/**
 * The customer's own chrome, inside the storefront's.
 *
 * Sits between StorefrontLayout and every `account/` page, and — through
 * layouts/settings/SettingsShell.vue — under the settings pages too, so a
 * shopper editing their profile never lands in the staff shell.
 *
 * The heading is the last rung of the server's own breadcrumb trail rather than
 * a second string each page has to repeat. Every controller here already sends
 * `Home / <this page>`, translated, so taking the title from it means the tab,
 * the trail and the H1 cannot drift apart.
 */
const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: App.Data.BreadcrumbData[];
}>();

type AccountNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    /**
     * The dashboard owns `/account` itself, which every other page in this
     * shell sits under — matching it on prefix would light it up everywhere.
     */
    exact?: boolean;
};

const navGroups: { label: string; items: AccountNavItem[] }[] = [
    {
        label: 'Shopping',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: House,
                exact: true,
            },
            { title: 'Orders', href: ordersIndex(), icon: Package },
            { title: 'Addresses', href: addressesRoute(), icon: MapPin },
            { title: 'Reviews', href: reviews(), icon: Star },
            {
                title: 'Recently viewed',
                href: recentlyViewed(),
                icon: Eye,
            },
        ],
    },
    {
        label: 'Settings',
        items: [
            { title: 'Profile', href: editProfile(), icon: UserRound },
            { title: 'Security', href: editSecurity(), icon: ShieldCheck },
        ],
    },
];

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isActive(item: AccountNavItem): boolean {
    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}

const heading = computed(() => breadcrumbs.at(-1)?.name ?? 'Your account');
</script>

<template>
    <div class="container flex flex-col gap-8 py-8">
        <div>
            <StoreBreadcrumbs :items="breadcrumbs" />

            <div class="mt-6">
                <p
                    class="text-electric text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                >
                    Your account
                </p>
                <h1
                    class="font-display text-ink mt-2 text-2xl font-extrabold tracking-[-0.03em] sm:text-4xl"
                >
                    {{ heading }}
                </h1>
            </div>
        </div>

        <div
            class="grid items-start gap-8 lg:grid-cols-[13.5rem_minmax(0,1fr)] lg:gap-12"
        >
            <!--
              Pills that wrap at narrow widths and stack into a rail from `lg`.
              Wrapping rather than scrolling on purpose: seven destinations that
              run off the side of a 360px phone are seven destinations nobody
              finds.
            -->
            <nav
                aria-label="Your account"
                class="flex flex-col gap-4 lg:sticky lg:top-28"
            >
                <div v-for="group in navGroups" :key="group.label">
                    <p
                        class="font-display text-muted-foreground text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                    >
                        {{ group.label }}
                    </p>

                    <ul
                        class="mt-2 flex flex-wrap gap-1.5 lg:flex-col lg:gap-1"
                    >
                        <li v-for="item in group.items" :key="toUrl(item.href)">
                            <Link
                                :href="item.href"
                                :aria-current="
                                    isActive(item) ? 'page' : undefined
                                "
                                class="focus-visible:outline-electric flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 lg:w-full"
                                :class="
                                    isActive(item)
                                        ? 'bg-tint-strong text-electric'
                                        : 'text-muted-foreground hover:bg-tint hover:text-ink'
                                "
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ item.title }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="min-w-0">
                <slot />
            </div>
        </div>
    </div>
</template>
