<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';
import { computed } from 'vue';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import StoreWordmark from '@/components/storefront/StoreWordmark.vue';
import {
    consentConfig,
    consentIsOffered,
    useConsentPreferences,
} from '@/lib/consent';
import type { SocialLink } from '@/types/global';
import { index as categoriesIndex } from '@/routes/categories';
import { show } from '@/routes/category';
import { catalog } from '@/routes';

const { categories } = defineProps<{ categories: NavCategory[] }>();

const page = usePage();

const year = new Date().getFullYear();

/**
 * Brand marks are not part of Lucide, which ships UI glyphs only, so they are
 * carried here as their official single-path outlines at a 24 viewBox.
 *
 * The URLs come from SocialSettings, shared on every response as
 * `storefront.socialLinks`; only profiles the store has actually filled in are
 * sent, so an unconfigured account leaves no dead icon behind. The paths below
 * are keyed by the same `icon` value the server sends.
 */
const marks: Record<string, string> = Object.fromEntries(
    [
        {
            key: 'facebook',
            label: 'Facebook',
            path: 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.5-3.92 3.77-3.92 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94Z',
        },
        {
            key: 'instagram',
            label: 'Instagram',
            path: 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.26.07 1.64.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.26.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23-.06-1.26-.07-1.64-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.26-.06 1.64-.07 4.85-.07Zm0 6.68a3.16 3.16 0 1 0 0 6.32 3.16 3.16 0 0 0 0-6.32Zm0 5.21a2.05 2.05 0 1 1 0-4.1 2.05 2.05 0 0 1 0 4.1Zm4.02-5.33a.74.74 0 1 1-1.47 0 .74.74 0 0 1 1.47 0Z',
        },
        {
            key: 'x',
            label: 'X',
            path: 'M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.65l-5.22-6.82-5.96 6.82H1.68l7.73-8.84L1.25 2.25h6.82l4.71 6.23 5.46-6.23Zm-1.16 17.52h1.83L7.08 4.13H5.11l11.97 15.64Z',
        },
        {
            key: 'youtube',
            label: 'YouTube',
            path: 'M21.58 7.19a2.51 2.51 0 0 0-1.77-1.78C18.25 5 12 5 12 5s-6.25 0-7.81.41a2.51 2.51 0 0 0-1.77 1.78C2 8.76 2 12 2 12s0 3.24.42 4.81a2.51 2.51 0 0 0 1.77 1.78C5.75 19 12 19 12 19s6.25 0 7.81-.41a2.51 2.51 0 0 0 1.77-1.78C22 15.24 22 12 22 12s0-3.24-.42-4.81ZM10 15.02V8.98L15.2 12 10 15.02Z',
        },
        {
            key: 'linkedin',
            label: 'LinkedIn',
            path: 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0Z',
        },
    ].map((mark) => [mark.key, mark.path]),
);

const socialLinks = computed<SocialLink[]>(
    () => page.props.storefront?.socialLinks ?? [],
);

/**
 * The mockup's Customer Care and About columns point at help, shipping,
 * returns, order-tracking, contact, about, terms and privacy pages. None of
 * them exists in this build and none has a route, so they are rendered as
 * plain, non-interactive `<span>`s styled like the links beside them: the
 * information architecture the design promises stays visible, and nothing
 * 404s. Give any of them a real controller and it becomes a `<Link>` here.
 */
const unroutedColumns = [
    {
        key: 'care',
        heading: 'Customer Care',
        items: [
            'Help Center',
            'Shipping Information',
            'Returns & Refunds',
            'Track Your Order',
            'Contact Us',
        ],
    },
] as const;

/**
 * The About column is the exception to the rule above: its two policy entries
 * now have somewhere to point, from LegalSettings. A URL that has not been
 * filled in still renders as a plain span, so the column keeps its shape.
 */
const consent = consentConfig();

const policies = computed(() => [
    { label: 'Terms & Conditions', url: consent.termsUrl },
    { label: 'Privacy Policy', url: consent.privacyPolicyUrl },
]);

const showConsentLink = consentIsOffered();

const { openConsentPreferences } = useConsentPreferences();
</script>

<template>
    <footer class="bg-footer text-white">
        <div
            class="container grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]"
        >
            <div>
                <StoreWordmark />

                <p class="mt-5 max-w-xs text-sm leading-relaxed text-white/55">
                    Everyday essentials, delivered across Kenya.<br />
                    Pay with M-Pesa or card at checkout.
                </p>

                <ul
                    v-if="socialLinks.length"
                    class="mt-7 flex items-center gap-4"
                >
                    <li v-for="social in socialLinks" :key="social.icon">
                        <a
                            :href="social.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="focus-visible:outline-electric block rounded-full text-white/70 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            <span class="sr-only">
                                {{ social.label }} (opens in a new tab)
                            </span>
                            <svg
                                class="size-5"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path :d="marks[social.icon]" />
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-bold text-white">Shop</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li
                        v-for="category in categories.slice(0, 6)"
                        :key="category.slug"
                    >
                        <Link
                            :href="show(category.slug)"
                            class="focus-visible:outline-electric rounded-sm text-white/55 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            {{ category.name }}
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="catalog()"
                            class="focus-visible:outline-electric rounded-sm text-white/55 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            All products
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="categoriesIndex()"
                            class="focus-visible:outline-electric rounded-sm text-white/55 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            All categories
                        </Link>
                    </li>
                </ul>
            </div>

            <div v-for="column in unroutedColumns" :key="column.key">
                <h2 class="text-xs font-bold text-white">
                    {{ column.heading }}
                </h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li v-for="item in column.items" :key="item">
                        <span class="text-white/55">{{ item }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-bold text-white">About</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li>
                        <span class="text-white/55">About ShopSmart</span>
                    </li>

                    <li v-for="policy in policies" :key="policy.label">
                        <a
                            v-if="policy.url"
                            :href="policy.url"
                            class="focus-visible:outline-electric rounded-sm text-white/55 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            {{ policy.label }}
                        </a>
                        <span v-else class="text-white/55">
                            {{ policy.label }}
                        </span>
                    </li>

                    <li v-if="showConsentLink">
                        <button
                            type="button"
                            class="focus-visible:outline-electric rounded-sm text-left text-white/55 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                            @click="openConsentPreferences"
                        >
                            Cookie preferences
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!--
          The rule stays on the band so it still runs edge to edge; only the
          copy inside it is pulled back onto the shared measure.
        -->
        <div class="border-t border-white/10">
            <div
                class="container flex flex-col gap-2 py-6 text-xs text-white/45 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>&copy; {{ year }} ShopSmart. All rights reserved.</p>
                <p class="flex items-center gap-1.5">
                    Made with
                    <Heart
                        class="size-3.5 fill-red-500 text-red-500"
                        aria-hidden="true"
                    />
                    <span class="sr-only">love</span>
                    in Kenya
                </p>
            </div>
        </div>
    </footer>
</template>
