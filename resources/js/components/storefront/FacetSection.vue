<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref, useId } from 'vue';

/**
 * A collapsible block of the filter sidebar.
 *
 * Kept in the DOM rather than unmounted while closed, so `aria-controls` always
 * points at something real and the shopper's scroll position inside a long
 * brand list survives a collapse.
 *
 * The hairline above each group is what separates one from the next inside the
 * sidebar card, so the first block in a stack drops it and the card's own
 * border does that job instead.
 */
const { open: defaultOpen = true } = defineProps<{
    title: string;
    open?: boolean;
}>();

const isOpen = ref(defaultOpen);
const contentId = useId();
</script>

<template>
    <section class="border-rule border-t pt-4 first:border-t-0 first:pt-0">
        <h3>
            <button
                type="button"
                class="focus-visible:outline-electric flex w-full items-center justify-between gap-2 rounded-sm text-left focus-visible:outline-2 focus-visible:outline-offset-4"
                :aria-expanded="isOpen"
                :aria-controls="contentId"
                @click="isOpen = !isOpen"
            >
                <span
                    class="font-display text-ink text-sm font-extrabold tracking-[-0.01em]"
                >
                    {{ title }}
                </span>
                <ChevronDown
                    class="text-muted-foreground size-4 transition-transform duration-200 motion-reduce:transition-none"
                    :class="isOpen ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </button>
        </h3>

        <div v-show="isOpen" :id="contentId" class="pt-3 pb-5">
            <slot />
        </div>
    </section>
</template>
