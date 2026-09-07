<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * The filter strip at the top of an index card.
 *
 * Search on the left at a fixed measure, the narrowing selects on the right.
 * That order is not decoration: search is the control a staff member reaches
 * for first and it is the only one that is free text, so it gets the stable
 * position and everything else flows after it.
 *
 * The strip is a `border-b` inside {@see AdminCard}, not a card of its own —
 * the toolbar, the table and the pagination are one object.
 *
 * `v-model:search` is the search term so the common case needs no slot at all;
 * every other control goes in the default slot.
 *
 * Not every table has free-text search. The activity log is filtered entirely
 * by dropdowns, and rendering a box that narrows nothing is a worse fault than
 * an inconsistent strip — so `searchable` turns it off and the slot controls
 * take the whole width. Do not reproduce this strip by hand to avoid the box.
 */
const {
    searchPlaceholder = 'Search…',
    showClear = false,
    searchable = true,
} = defineProps<{
    searchPlaceholder?: string;
    searchLabel?: string;
    /** Reveal the reset control — pass the page's `isFiltered`. */
    showClear?: boolean;
    /** False when the server offers no free-text search for this table. */
    searchable?: boolean;
}>();

const search = defineModel<string>('search', { default: '' });

defineEmits<{ clear: [] }>();
</script>

<template>
    <div
        class="flex flex-col gap-3 border-b px-5 py-3 lg:flex-row lg:items-center lg:justify-between"
    >
        <div v-if="searchable" class="relative w-full lg:max-w-xs">
            <Search
                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                aria-hidden="true"
            />
            <Input
                v-model="search"
                type="search"
                class="pl-8"
                :placeholder="searchPlaceholder"
                :aria-label="searchLabel ?? searchPlaceholder"
            />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <slot />

            <Button
                v-if="showClear"
                variant="ghost"
                size="sm"
                @click="$emit('clear')"
            >
                <X class="size-4" aria-hidden="true" />
                Clear
            </Button>
        </div>
    </div>
</template>
