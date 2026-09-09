<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * One narrowing dropdown in an {@see AdminFilterBar}, and the single place the
 * "no filter" value is translated.
 *
 * `useIndexTable` spells an inactive filter as the empty string, and that is
 * load-bearing: `activeQuery` drops empty values so a filter that narrows
 * nothing never reaches the URL, `isFiltered` reads them to decide whether the
 * Clear button and the filtered empty state appear, and `clear()` writes them
 * back. reka-ui refuses that same empty string as a `SelectItem` value — it
 * reserves it for "the selection has been cleared" — so the two vocabularies
 * cannot meet directly.
 *
 * `null` is reka-ui's own empty value (`isNullish`), and unlike the empty
 * string an item is allowed to carry it, so the "All …" row stays a real,
 * re-selectable option rather than something the staff member can only get
 * back to by reloading. Translating here rather than in each page keeps the
 * eleven index screens from each inventing their own sentinel, which is the
 * same failure `useIndexTable` was written to end.
 *
 * The options themselves stay in the default slot as `SelectItem`s: the pages
 * feed them from a dozen differently shaped prop lists — indented category
 * trees, bare string arrays, id/label pairs — and normalising all of that into
 * one array shape moves work into every call site to save none here.
 */
const { allLabel, label } = defineProps<{
    /** The "no filter" row, and the trigger's text while nothing is chosen. */
    allLabel: string;
    /** The accessible name: the filter strip carries no visible labels. */
    label: string;
    class?: HTMLAttributes['class'];
}>();

/** The page's filter field, where `''` means the filter is off. */
const model = defineModel<string>({ required: true });

/**
 * `Select` renders a fragment, so `class` and the accessible name are declared
 * props forwarded to the trigger rather than fallthrough attributes.
 */
defineOptions({ inheritAttrs: false });

const selected = computed<string | null>({
    get: () => (model.value === '' ? null : model.value),
    set: (value) => {
        model.value = value ?? '';
    },
});
</script>

<template>
    <Select v-model="selected">
        <SelectTrigger :class="$props.class" :aria-label="label">
            <SelectValue :placeholder="allLabel" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem :value="null">{{ allLabel }}</SelectItem>
            <slot />
        </SelectContent>
    </Select>
</template>
