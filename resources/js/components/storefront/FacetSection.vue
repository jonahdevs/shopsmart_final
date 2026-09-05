<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref, useId } from 'vue';

/**
 * A collapsible block of the filter sidebar.
 *
 * Kept in the DOM rather than unmounted while closed, so `aria-controls` always
 * points at something real and the shopper's scroll position inside a long
 * brand list survives a collapse.
 */
const { open: defaultOpen = true } = defineProps<{
    title: string;
    open?: boolean;
}>();

const isOpen = ref(defaultOpen);
const contentId = useId();
</script>

<template>
    <section class="border-rule border-t pt-4">
        <h3>
            <button
                type="button"
                class="focus-visible:outline-electric flex w-full items-center justify-between gap-2 rounded-xs text-left focus-visible:outline-2 focus-visible:outline-offset-4"
                :aria-expanded="isOpen"
                :aria-controls="contentId"
                @click="isOpen = !isOpen"
            >
                <span
                    class="font-display text-foreground text-[0.6875rem] font-bold tracking-[0.16em] uppercase"
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
