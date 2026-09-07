<script setup lang="ts">
/**
 * The title block every admin page opens with.
 *
 * Exists so the heading level, spacing and the placement of page actions are
 * decided once. Pages pass their actions through the `actions` slot rather than
 * laying out their own row, which is what keeps the alignment identical across
 * thirty-four screens.
 *
 * The eyebrow is the section a screen belongs to — "Catalog" above Products —
 * and it is the same device the storefront opens every section with, set in the
 * same display face. It is what makes the back office read as the same product
 * as the shop rather than a bolted-on tool.
 */
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <p
                v-if="eyebrow"
                class="text-primary font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
            >
                {{ eyebrow }}
            </p>

            <h1 class="font-display text-2xl font-extrabold tracking-[-0.02em]">
                {{ title }}
            </h1>

            <p v-if="description" class="text-muted-foreground text-sm">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
