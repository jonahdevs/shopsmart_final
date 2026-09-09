<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * The strip that appears between the filters and the table once rows are
 * ticked, holding whatever can be done to them.
 *
 * It knows nothing about what the rows are. The noun is a prop and the actions
 * are a slot, because reviews and orders get the same strip and the moment one
 * of them needs "Approve" the wording would have to come out of here anyway.
 * What it does own is the honest sentence about the selection, which is the
 * part every table would otherwise write slightly differently.
 *
 * "on this page" is not padding. Selection resets on every visit — see
 * `useRowSelection` for why — and the count has to say so, because "12
 * selected" on a table of 400 invites the reader to assume the other pages came
 * too. A bulk bar that lets somebody act on rows they cannot account for is the
 * failure mode this component exists to avoid.
 *
 * The strip is a `border-b` inside {@see AdminCard}, tinted so it reads as a
 * temporary mode over the table rather than a second toolbar. It renders
 * nothing at all when the selection is empty: a permanently visible bar with
 * disabled buttons trains staff to ignore the row it occupies.
 */
const { count, itemLabel, itemLabelPlural } = defineProps<{
    /** How many rows are ticked. Zero hides the strip. */
    count: number;
    /** What one row is, in the reader's words: "product", "review". */
    itemLabel: string;
    /** The plural of the same, spelled out rather than derived — "categories". */
    itemLabelPlural: string;
}>();

defineEmits<{ clear: [] }>();

const noun = computed(() => (count === 1 ? itemLabel : itemLabelPlural));
</script>

<template>
    <div
        v-if="count > 0"
        class="bg-muted/50 flex flex-col gap-3 border-b px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <!--
          `role="status"` rather than a bare paragraph: ticking a row is a
          mouse gesture with no other spoken feedback, so the running count is
          the only thing that tells a screen reader user what they now hold.
        -->
        <p role="status" aria-live="polite" class="text-sm font-medium">
            {{ count }} {{ noun }} selected on this page
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <slot />

            <Button variant="ghost" size="sm" @click="$emit('clear')">
                <X class="size-4" aria-hidden="true" />
                Clear selection
            </Button>
        </div>
    </div>
</template>
