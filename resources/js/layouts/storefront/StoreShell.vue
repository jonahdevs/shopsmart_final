<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import ConsentBanner from '@/components/storefront/ConsentBanner.vue';
import StoreFooter from '@/components/storefront/StoreFooter.vue';
import StoreHeader from '@/components/storefront/StoreHeader.vue';
import StoreNewsletter from '@/components/storefront/StoreNewsletter.vue';
import StoreUtilityBar from '@/components/storefront/StoreUtilityBar.vue';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();

/**
 * Shared from HandleInertiaRequests so the nav is identical on every storefront
 * page and does not have to be threaded through each controller.
 */
const categories = computed<NavCategory[]>(
    () => page.props.storefront?.navCategories ?? [],
);
</script>

<template>
    <!--
      `.storefront` remaps the shadcn semantic tokens onto the brand palette and
      pins light mode; see resources/css/app.css. Dark mode stays a staff-only
      affordance in the admin.

      This shell declares NO props on purpose: StorefrontLayout is resolved by
      the `layout:` switch in app.ts, so any `defineOptions({ layout: {...} })`
      a page passed would fall through to `$attrs` and be stamped on this root
      div. Storefront pages render StoreBreadcrumbs.vue in-page instead.
    -->
    <div
        class="storefront bg-background text-foreground flex min-h-screen flex-col"
    >
        <!--
          First focusable thing on the page, and visible only once focused.
          Every storefront page opens with a utility bar, a header and a
          category stripe; without this, reaching the actual content by keyboard
          means tabbing past two dozen links on every single navigation.
        -->
        <a
            href="#main-content"
            class="bg-electric focus-visible:outline-ink sr-only rounded-lg px-4 py-2 text-sm font-semibold text-white focus-visible:not-sr-only focus-visible:absolute focus-visible:top-3 focus-visible:left-3 focus-visible:z-100 focus-visible:outline-2 focus-visible:outline-offset-2"
        >
            Skip to content
        </a>

        <StoreUtilityBar />

        <StoreHeader :categories="categories" />

        <!--
          `tabindex="-1"` so the skip link can actually move focus here. Without
          it the browser scrolls to the landmark but leaves focus in the header,
          and the next Tab returns to the navigation the shopper just skipped.
        -->
        <main id="main-content" tabindex="-1" class="flex-1 focus:outline-none">
            <slot />
        </main>

        <StoreNewsletter />

        <StoreFooter :categories="categories" />

        <!--
          Last in the shell so it lays over the page. It renders nothing at all
          unless the store actually asks about a category, and it never loads a
          tag itself — the server does that, for the categories already granted.
        -->
        <ConsentBanner />

        <Toaster position="top-center" />
    </div>
</template>
