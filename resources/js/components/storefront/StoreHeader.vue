<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Heart, Scale, Search, ShoppingCart, UserRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { index as compareIndex } from '@/routes/compare';
import { index as cartIndex } from '@/routes/cart';
import { index as wishlistIndex } from '@/routes/wishlist';
import CategoryStripe from '@/components/storefront/CategoryStripe.vue';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import { dashboard, login } from '@/routes';
import { catalog, home } from '@/routes';

const { categories } = defineProps<{ categories: NavCategory[] }>();

const page = usePage();

const query = ref<string>('');

/**
 * Shared from HandleInertiaRequests on every storefront response, and derived
 * from the session rather than a query, so reading it here costs nothing.
 */
const shopper = computed(() => page.props.storefront?.shopper ?? null);

/** The tray links, so the markup below stays one loop rather than three. */
const trays = computed(() => [
    {
        key: 'wishlist',
        href: wishlistIndex(),
        icon: Heart,
        label: 'Wishlist',
        count: shopper.value?.wishlistCount ?? 0,
    },
    {
        key: 'compare',
        href: compareIndex(),
        icon: Scale,
        label: 'Compare',
        count: shopper.value?.compareCount ?? 0,
    },
    {
        key: 'cart',
        href: cartIndex(),
        icon: ShoppingCart,
        label: 'Cart',
        count: shopper.value?.cartCount ?? 0,
    },
]);

/**
 * The header lives in the persistent layout, so it is constructed once and
 * never again — seeding the box from the URL at setup would leave the last
 * search sitting in it for the rest of the session. Track Inertia's url
 * instead, so navigating away from a search result clears the field.
 */
watch(
    () => page.url,
    (url) => {
        query.value =
            new URL(url, window.location.origin).searchParams.get('q') ?? '';
    },
    { immediate: true },
);

function search(): void {
    router.get(catalog.url(), query.value ? { q: query.value } : {}, {
        preserveState: false,
    });
}
</script>

<template>
    <header class="bg-ink sticky top-0 z-50 text-white">
        <div
            class="flex items-center gap-4 px-4 py-3.5 sm:gap-6 sm:px-6 lg:px-8"
        >
            <Link
                :href="home()"
                class="font-display focus-visible:outline-electric text-lg font-black tracking-[-0.045em] whitespace-nowrap uppercase focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Shop<span class="text-electric">smart</span>
            </Link>

            <!--
              `min-w-0` because a flex item will not shrink past its content's
              intrinsic width by default, and an `<input>` carries one — without
              it the header is wider than a 320px viewport and the whole
              document picks up a horizontal scrollbar.
            -->
            <form
                class="relative ml-auto w-full max-w-xl min-w-0"
                role="search"
                @submit.prevent="search"
            >
                <label for="store-search" class="sr-only">
                    Search the catalogue
                </label>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-white/45"
                    aria-hidden="true"
                />
                <input
                    id="store-search"
                    v-model="query"
                    type="search"
                    name="q"
                    placeholder="Search the catalogue"
                    autocomplete="off"
                    class="focus:border-electric w-full rounded-xs border border-white/15 bg-white/8 py-2 pr-3 pl-9 text-sm text-white placeholder:text-white/45 focus:bg-white/12 focus:outline-none"
                />
            </form>

            <Link
                :href="page.props.auth.user ? dashboard() : login()"
                class="focus-visible:outline-electric flex shrink-0 items-center gap-2 rounded-xs px-1 py-1 text-sm text-white/80 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                <UserRound class="size-5" aria-hidden="true" />
                <span class="hidden sm:inline">
                    {{ page.props.auth.user ? 'Account' : 'Sign in' }}
                </span>
            </Link>

            <Link
                v-for="tray in trays"
                :key="tray.key"
                :href="tray.href"
                class="focus-visible:outline-electric relative shrink-0 rounded-xs p-1 text-white/80 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4"
                :aria-label="`${tray.label}, ${tray.count} items`"
            >
                <component :is="tray.icon" class="size-5" aria-hidden="true" />
                <span
                    v-if="tray.count > 0"
                    class="bg-electric font-display absolute -top-1 -right-1 min-w-4 rounded-full px-1 text-center text-[10px] leading-4 font-bold text-white tabular-nums"
                    aria-hidden="true"
                >
                    {{ tray.count > 99 ? '99+' : tray.count }}
                </span>
            </Link>
        </div>

        <CategoryStripe :categories="categories" />
    </header>
</template>
