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
            <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
            <h2
                class="font-display text-foreground mt-3 text-xl font-black tracking-[-0.03em] uppercase sm:text-2xl"
            >
                {{ title }}
            </h2>
        </div>

        <Link
            v-if="viewAllHref"
            :href="viewAllHref"
            class="font-display text-electric hover:border-electric focus-visible:outline-electric shrink-0 border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
        >
            {{ viewAllLabel ?? 'View all' }}
        </Link>
    </div>
</template>
