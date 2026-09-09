<script setup lang="ts">
import StoreWordmark from '@/components/storefront/StoreWordmark.vue';

/**
 * The lockup at the top of the staff rail.
 *
 * Deliberately the storefront's own wordmark rather than a separate staff mark:
 * the back office is the same shop seen from behind, and the rail is charcoal,
 * which is the chrome the wordmark was drawn for. Nothing is stacked under it —
 * the rail itself already says which side of the app this is.
 */
</script>

<template>
    <div class="flex min-w-0 px-2 py-1">
        <StoreWordmark size="sm" />
    </div>
</template>
