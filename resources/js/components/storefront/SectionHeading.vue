<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';

/**
 * Section marker for the storefront. The short electric rule above the title is
 * the header pinstripe repeated at section scale — the one structural device
 * that ties the page together.
 */
defineProps<{
    title: string;
    viewAllHref?: NonNullable<InertiaLinkProps['href']>;
    viewAllLabel?: string;
}>();
</script>

<template>
    <div class="flex items-end justify-between gap-4">
        <div>
            <span class="block h-0.5 w-8 bg-electric" aria-hidden="true" />
            <h2
                class="mt-3 font-display text-xl font-black tracking-[-0.03em] text-foreground uppercase sm:text-2xl"
            >
                {{ title }}
            </h2>
        </div>

        <Link
            v-if="viewAllHref"
            :href="viewAllHref"
            class="shrink-0 border-b border-transparent pb-0.5 font-display text-sm font-bold text-electric transition-colors hover:border-electric focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric"
        >
            {{ viewAllLabel ?? 'View all' }}
        </Link>
    </div>
</template>
