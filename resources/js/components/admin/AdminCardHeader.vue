<script setup lang="ts">
import { ChevronDown, type LucideIcon } from '@lucide/vue';

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
 *
 * `collapsible` turns the title into the disclosure control for a card whose
 * body can be folded away. The open state is owned by the caller rather than
 * here, because what a card opens on is a decision about its *content* — an
 * empty optional section starts folded, a filled one does not — and only the
 * caller can see the content.
 */
const { collapsible = false, open = true } = defineProps<{
    title: string;
    icon?: LucideIcon;
    /** Renders the title as a disclosure button. The caller owns `open`. */
    collapsible?: boolean;
    open?: boolean;
    /** The id of the body this strip discloses, for `aria-controls`. */
    controls?: string;
}>();

defineEmits<{ toggle: [] }>();
</script>

<template>
    <div class="flex items-center justify-between gap-3 border-b px-5 py-3">
        <div v-if="!collapsible" class="flex min-w-0 items-center gap-2">
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

        <h2 v-else class="mr-auto flex min-w-0 items-center">
            <!--
              `type="button"`: this strip sits inside the page's `<Form>` on
              every form screen, and a bare button there submits it.
            -->
            <button
                type="button"
                class="focus-visible:ring-ring flex min-w-0 items-center gap-2 rounded-sm text-left focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                :aria-expanded="open"
                :aria-controls="controls"
                @click="$emit('toggle')"
            >
                <component
                    :is="icon"
                    v-if="icon"
                    class="text-muted-foreground size-4 shrink-0"
                    aria-hidden="true"
                />
                <span
                    class="font-display truncate text-xs font-bold tracking-[0.08em] uppercase"
                >
                    {{ title }}
                </span>
            </button>
        </h2>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>

        <!--
          The chevron is a second, redundant hit target for the same toggle —
          the far right of the strip is where a mouse goes for it, and with an
          actions slot in the middle the title button no longer reaches there.
          Hidden from assistive technology and off the tab order on purpose:
          the title button above already carries the name, the state and the
          keyboard route, and announcing the toggle twice is worse than not
          announcing this one at all.
        -->
        <button
            v-if="collapsible"
            type="button"
            class="text-muted-foreground shrink-0"
            tabindex="-1"
            aria-hidden="true"
            @click="$emit('toggle')"
        >
            <ChevronDown
                class="size-4 transition-transform duration-200 motion-reduce:transition-none"
                :class="open ? 'rotate-180' : ''"
            />
        </button>
    </div>
</template>
