<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

/**
 * The title strip at the top of an {@see AdminCard}.
 *
 * Small, uppercase and letter-spaced rather than a heading scale down from the
 * page H1. On a screen carrying six panels, a card title is a label for a
 * region, not a second-level heading competing with the page's own — treating
 * it as one is what makes a dense admin screen read as a pile of headings.
 *
 * The optional icon is a wayfinding aid on long scrolling screens, not
 * decoration: give a panel one only when the panel repeats across screens
 * (payment, delivery, totals) and the icon is the thing a staff member scans
 * for.
 */
defineProps<{
    title: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div class="flex items-center justify-between gap-3 border-b px-5 py-3">
        <div class="flex min-w-0 items-center gap-2">
            <component
                :is="icon"
                v-if="icon"
                class="text-muted-foreground size-4 shrink-0"
                aria-hidden="true"
            />
            <h2
                class="font-display truncate text-xs font-bold tracking-[0.08em] uppercase"
            >
                {{ title }}
            </h2>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
