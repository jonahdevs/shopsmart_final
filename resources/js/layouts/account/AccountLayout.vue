<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Eye,
    Heart,
    House,
    LogOut,
    MapPin,
    Package,
    ShieldCheck,
    Star,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useInitials } from '@/composables/useInitials';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import {
    addresses as addressesRoute,
    dashboard,
    recentlyViewed,
    reviews,
} from '@/routes/account';
import { index as ordersIndex } from '@/routes/orders';
import { index as wishlistIndex } from '@/routes/wishlist';
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
            /*
              Saved items live on the storefront at `/wishlist`, not under
              `/account`, because a guest has one too. It is listed here anyway:
              a signed-in shopper looking for the things they saved looks in
              their account, and sending them to the header instead is a worse
              answer than a link that leaves this shell.
            */
            { title: 'Saved items', href: wishlistIndex(), icon: Heart },
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

/**
 * Read off the shared props rather than asked of each controller: every page in
 * this shell already carries `auth.user`, and a second server prop for the same
 * three strings is a prop four controllers have to remember to send.
 */
const page = usePage();

const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

/**
 * Signing out has to clear the prefetch cache, or the next person on this
 * device is served the previous shopper's pages straight out of memory.
 * Same reason `UserMenuContent` does it — the two must not diverge.
 */
function forgetCachedPages(): void {
    router.flushAll();
}
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
            <div class="flex flex-col gap-5 lg:sticky lg:top-28">
                <!--
                  Who you are signed in as, before the list of what you can do
                  with it. Worth the space on a shared machine — the storefront
                  header only shows an account icon, so without this the first
                  confirmation of whose account this is would be the email
                  halfway down the dashboard.
                -->
                <div
                    class="border-rule shadow-card rounded-lg border bg-white px-4 py-5 text-center"
                >
                    <Avatar class="mx-auto size-14">
                        <AvatarImage
                            v-if="user.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                        />
                        <AvatarFallback
                            class="bg-electric font-display text-base font-bold text-white"
                        >
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>

                    <p
                        class="font-display text-ink mt-3 truncate text-sm font-bold"
                    >
                        {{ user.name }}
                    </p>
                    <p class="text-muted-foreground truncate text-xs">
                        {{ user.email }}
                    </p>
                </div>

                <!--
                  Pills that wrap at narrow widths and stack into a rail from
                  `lg`. Wrapping rather than scrolling on purpose: eight
                  destinations that run off the side of a 360px phone are eight
                  destinations nobody finds.
                -->
                <nav aria-label="Your account" class="flex flex-col gap-4">
                    <div v-for="group in navGroups" :key="group.label">
                        <p
                            class="font-display text-muted-foreground text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                        >
                            {{ group.label }}
                        </p>

                        <ul
                            class="mt-2 flex flex-wrap gap-1.5 lg:flex-col lg:gap-1"
                        >
                            <li
                                v-for="item in group.items"
                                :key="toUrl(item.href)"
                            >
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

                <!--
                  A real POST — `logout()` is a POST route definition, so the
                  Link issues one rather than following a GET that would leave
                  the session standing. Separated from the nav above by a rule
                  because it is the one item here that ends the visit.
                -->
                <div class="border-rule border-t pt-4">
                    <Link
                        :href="logout()"
                        as="button"
                        type="button"
                        data-test="account-logout-button"
                        class="text-muted-foreground hover:bg-destructive/5 hover:text-destructive focus-visible:outline-destructive flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                        @click="forgetCachedPages"
                    >
                        <LogOut class="size-4 shrink-0" aria-hidden="true" />
                        Sign out
                    </Link>
                </div>
            </div>

            <div class="min-w-0">
                <slot />
            </div>
        </div>
    </div>
</template>
