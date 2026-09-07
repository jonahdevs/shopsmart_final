<script setup lang="ts">
import StoreWordmark from '@/components/storefront/StoreWordmark.vue';

/**
 * The lockup at the top of the staff rail.
 *
 * Deliberately the storefront's own wordmark rather than a separate staff mark:
 * the back office is the same shop seen from behind, and the rail is charcoal,
 * which is the chrome the wordmark was drawn for. The "Admin" label under it is
 * what distinguishes the two, so a staff member glancing at a tab knows which
 * side of the app they are on without a second logo existing.
 */
</script>

<template>
    <div class="flex min-w-0 flex-col gap-0.5 px-2 py-1">
        <StoreWordmark size="sm" />
        <span
            class="text-[0.5625rem] font-bold tracking-[0.22em] text-white/45 uppercase"
        >
            Admin
        </span>
    </div>
</template>
