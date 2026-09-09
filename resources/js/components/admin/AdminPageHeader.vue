<script setup lang="ts">
/**
 * The title block every admin page opens with.
 *
 * Exists so the heading level, spacing and the placement of page actions are
 * decided once. Pages pass their actions through the `actions` slot rather than
 * laying out their own row, which is what keeps the alignment identical across
 * thirty-four screens.
 *
 * There is deliberately no eyebrow. It used to carry the section a screen
 * belongs to — "Catalog" above Products — which is the group name the rail is
 * already showing, highlighted, a few centimetres to the left. Two answers to
 * the same question, and the breadcrumb above gives a third. The storefront
 * keeps its own eyebrow because there is no rail out there to repeat.
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
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
