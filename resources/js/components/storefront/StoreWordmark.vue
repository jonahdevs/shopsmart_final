<script setup lang="ts">
/**
 * The storefront wordmark: SHOP in white, SMART in the electric blue.
 *
 * Staff-side `AppLogo` is a mark-plus-name lockup for a sidebar; the shop needs
 * a typographic wordmark that reads at a glance on the navy chrome, so this is
 * a storefront-specific sibling rather than a variant of that component. Header
 * and footer both compose it, which is what keeps the two identical.
 */
withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'md' });
</script>

<template>
    <span
        class="font-display leading-none font-extrabold tracking-[-0.03em] whitespace-nowrap text-white uppercase"
        :class="size === 'sm' ? 'text-lg' : 'text-xl sm:text-2xl'"
    >
        Shop<span class="text-electric">Smart</span>
    </span>
</template>
