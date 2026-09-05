<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
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
        <StoreUtilityBar />

        <StoreHeader :categories="categories" />

        <main class="flex-1">
            <slot />
        </main>

        <StoreNewsletter />

        <StoreFooter :categories="categories" />

        <Toaster position="top-center" />
    </div>
</template>
