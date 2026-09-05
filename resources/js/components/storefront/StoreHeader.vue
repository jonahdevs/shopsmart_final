<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Heart, Scale, Search, ShoppingCart, UserRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CategoryStripe from '@/components/storefront/CategoryStripe.vue';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import StoreWordmark from '@/components/storefront/StoreWordmark.vue';
import { index as cartIndex } from '@/routes/cart';
import { index as compareIndex } from '@/routes/compare';
import { index as wishlistIndex } from '@/routes/wishlist';
import { catalog, dashboard, home, login } from '@/routes';

const { categories } = defineProps<{ categories: NavCategory[] }>();

const page = usePage();

const query = ref<string>('');

/**
 * Shared from HandleInertiaRequests on every storefront response, and derived
 * from the session rather than a query, so reading it here costs nothing.
 */
const shopper = computed(() => page.props.storefront?.shopper ?? null);

/**
 * The tray links, so the markup below stays one loop rather than three.
 *
 * Wishlist and Cart carry their label from `md` up, matching the approved
 * design; Compare has no label there but keeps its own destination and count —
 * dropping the link would take a real page out of the storefront's reach.
 */
const trays = computed(() => [
    {
        key: 'wishlist',
        href: wishlistIndex(),
        icon: Heart,
        label: 'Wishlist',
        labelled: true,
        count: shopper.value?.wishlistCount ?? 0,
    },
    {
        key: 'compare',
        href: compareIndex(),
        icon: Scale,
        label: 'Compare',
        labelled: false,
        count: shopper.value?.compareCount ?? 0,
    },
    {
        key: 'cart',
        href: cartIndex(),
        icon: ShoppingCart,
        label: 'Cart',
        labelled: false,
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
    <header class="bg-header sticky top-0 z-50 text-white">
        <!--
          `flex-wrap` rather than a duplicated mobile field: below `md` the
          search box takes a full row of its own under the wordmark and the
          tray cluster, and from `md` up the three sit on one line with the
          field centred between them.
        -->
        <div
            class="container flex flex-wrap items-center gap-x-4 gap-y-3 py-3 md:flex-nowrap md:py-4"
        >
            <Link
                :href="home()"
                class="focus-visible:outline-electric order-1 shrink-0 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4"
                aria-label="ShopSmart home"
            >
                <StoreWordmark />
            </Link>

            <!--
              `min-w-0` because a flex item will not shrink past its content's
              intrinsic width by default, and an `<input>` carries one — without
              it the header is wider than a 360px viewport and the whole
              document picks up a horizontal scrollbar.
            -->
            <form
                class="focus-within:outline-electric order-3 flex w-full min-w-0 items-center overflow-hidden rounded-full bg-white focus-within:outline-2 focus-within:outline-offset-2 md:order-2 md:mx-auto md:w-auto md:max-w-2xl md:flex-1"
                role="search"
                @submit.prevent="search"
            >
                <label for="store-search" class="sr-only">
                    Search the catalogue
                </label>
                <input
                    id="store-search"
                    v-model="query"
                    type="search"
                    name="q"
                    placeholder="Search for products, brands and more..."
                    autocomplete="off"
                    class="text-ink placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent py-2.5 pr-2 pl-5 text-sm focus:outline-none"
                />
                <button
                    type="submit"
                    class="bg-electric flex w-14 shrink-0 items-center justify-center self-stretch text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white"
                >
                    <Search class="size-4.5" aria-hidden="true" />
                    <span class="sr-only">Search</span>
                </button>
            </form>

            <div
                class="order-2 ml-auto flex shrink-0 items-center gap-0.5 md:order-3 md:gap-1"
            >
                <Link
                    v-for="tray in trays"
                    :key="tray.key"
                    :href="tray.href"
                    class="focus-visible:outline-electric flex items-center gap-2 rounded-full p-3 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2"
                    :aria-label="`${tray.label}, ${tray.count} items`"
                >
                    <!--
                      The count is pinned to the glyph rather than to the link,
                      so it sits on the icon's shoulder whether or not the label
                      beside it is showing at this breakpoint.
                    -->
                    <span class="relative">
                        <component
                            :is="tray.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                        <span
                            v-if="tray.count > 0"
                            class="bg-electric ring-header font-display absolute -top-2 -right-2.5 min-w-[1.125rem] rounded-full px-1 text-center text-[10px] leading-[1.125rem] font-bold text-white tabular-nums ring-2"
                            aria-hidden="true"
                        >
                            {{ tray.count > 99 ? '99+' : tray.count }}
                        </span>
                    </span>
                    <span
                        v-if="tray.labelled"
                        class="hidden md:inline"
                        aria-hidden="true"
                    >
                        {{ tray.label }}
                    </span>
                </Link>

                <Link
                    :href="page.props.auth.user ? dashboard() : login()"
                    class="focus-visible:outline-electric flex items-center gap-2 rounded-full p-3 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2"
                    :aria-label="page.props.auth.user ? 'Account' : 'Sign in'"
                >
                    <UserRound class="size-5" aria-hidden="true" />
                    <span class="hidden md:inline" aria-hidden="true">
                        {{ page.props.auth.user ? 'Account' : 'Sign in' }}
                    </span>
                </Link>
            </div>
        </div>

        <CategoryStripe :categories="categories" />
    </header>
</template>
