<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

/**
 * What a table says when it has nothing to show.
 *
 * The distinction that matters is `filtered`. "No products yet" and "no
 * products match these filters" are different situations with different
 * remedies, and a table that says the first when it means the second sends a
 * staff member looking for a bug in the catalog. Index pages know which one
 * they are in — they hold the filter state — so they pass it, and this decides
 * whether the invitation to create something is even appropriate.
 */
defineProps<{
    icon?: LucideIcon;
    title: string;
    description?: string;
    /** True when filters are narrowing the result, not when the table is empty. */
    filtered?: boolean;
}>();
</script>

<template>
    <div class="flex flex-col items-center px-6 py-16 text-center">
        <component
            :is="icon"
            v-if="icon"
            class="text-muted-foreground/40 size-8"
            aria-hidden="true"
        />

        <p class="font-display mt-3 text-sm font-bold">{{ title }}</p>

        <p
            v-if="description"
            class="text-muted-foreground mt-1 max-w-sm text-sm"
        >
            {{ description }}
        </p>

        <!--
          Only offered when nothing is being filtered away. Inviting someone to
          create a record because their search matched nothing is how duplicates
          get made.
        -->
        <div v-if="$slots.action && !filtered" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
