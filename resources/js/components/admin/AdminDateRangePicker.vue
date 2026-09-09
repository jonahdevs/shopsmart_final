<script setup lang="ts">
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import type { DateRange, DateValue } from 'reka-ui';
import { computed, ref, shallowRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { formatIsoDate } from '@/lib/utils';

/**
 * The one date control the back office uses.
 *
 * Deliberately dumb: props in, one event out. It knows nothing about the
 * dashboard, an index table or the query string, which is what lets the same
 * control sit in a page header and inside a filter strip. The caller decides
 * what a change means — a router visit on the overview, a `useIndexTable` field
 * on a list screen.
 *
 * The preset list comes from the server and a chosen preset is emitted as its
 * KEY, never as the two dates it resolves to. That is the whole reason the
 * control exists in this shape: a link to "last 30 days" has to still mean the
 * last thirty days when it is opened next month, and it only can if the client
 * never writes the resolved dates into the URL.
 *
 * The label is computed here rather than taken as a prop so it changes in the
 * same frame as the click. On an index screen the visit is debounced by 300ms
 * and then has to round-trip; a server-owned label would leave the trigger
 * showing the previous window for the whole of it.
 */
const {
    presets,
    preset,
    from,
    to,
    placeholder = 'All time',
    clearable = false,
    align = 'end',
    ariaLabel = 'Filter by date range',
} = defineProps<{
    presets: App.Data.AdminDateRangeOptionData[];
    /** The active preset key, or `''` when no range is applied. */
    preset: string;
    /** The resolved window, `YYYY-MM-DD`, or `''` when none. */
    from: string;
    to: string;
    /** What the trigger reads when no range is applied. */
    placeholder?: string;
    /** Offer a row that turns the range off. Index screens want it; the
        dashboard, which always reports some period, does not. */
    clearable?: boolean;
    align?: 'start' | 'end';
    ariaLabel?: string;
}>();

const emit = defineEmits<{
    /** `('last_30_days', '', '')` for a preset, `('custom', from, to)` for a
        drawn window, `('', '', '')` when the range is cleared. */
    change: [preset: string, from: string, to: string];
}>();

const isOpen = ref(false);

function toDateValue(value: string): DateValue | undefined {
    if (value === '') {
        return undefined;
    }

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
}

/*
  A draft rather than a computed off the props: a range takes two clicks, and
  between them the calendar has a start and no end. Driving it straight from
  the props would discard the first click, because nothing has been emitted yet.
*/
const draft = shallowRef<DateRange>({
    start: toDateValue(from),
    end: toDateValue(to),
});

watch(
    () => [from, to],
    ([nextFrom, nextTo]) => {
        draft.value = {
            start: toDateValue(nextFrom ?? ''),
            end: toDateValue(nextTo ?? ''),
        };
    },
);

/** The calendar cannot offer a window that has not happened yet. */
const maxDate = computed(() => today(getLocalTimeZone()));

const activePresetLabel = computed(
    () => presets.find((option) => option.value === preset)?.label,
);

const triggerLabel = computed(() => {
    if (activePresetLabel.value !== undefined) {
        return activePresetLabel.value;
    }

    if (from === '' && to === '') {
        return placeholder;
    }

    if (from !== '' && to !== '' && from !== to) {
        return `${formatIsoDate(from)} – ${formatIsoDate(to)}`;
    }

    return formatIsoDate(from !== '' ? from : to);
});

/** True while the control is narrowing nothing, so it reads as a placeholder. */
const isUnset = computed(() => preset === '' && from === '' && to === '');

function choose(value: string): void {
    isOpen.value = false;
    emit('change', value, '', '');
}

function pick(range: DateRange | undefined): void {
    draft.value = { start: range?.start, end: range?.end };

    // The first click of a two-click gesture: a start with no end is not yet a
    // window, and emitting it would filter the screen to a single open-ended
    // range the reader has not finished asking for.
    if (range?.start === undefined || range?.end === undefined) {
        return;
    }

    isOpen.value = false;
    emit('change', 'custom', range.start.toString(), range.end.toString());
}
</script>

<template>
    <Popover v-model:open="isOpen">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                class="gap-2 font-normal"
                :class="isUnset ? 'text-muted-foreground' : ''"
                :aria-label="ariaLabel"
            >
                <!--
                  The lighter stroke is deliberate: at 16px the default weight
                  reads heavier than the label beside it, and the icon is a
                  prefix rather than the thing being pointed at.
                -->
                <CalendarIcon
                    class="size-4"
                    :stroke-width="1.8"
                    aria-hidden="true"
                />
                {{ triggerLabel }}
            </Button>
        </PopoverTrigger>

        <PopoverContent class="w-auto p-0" :align="align">
            <div class="flex flex-col sm:flex-row">
                <!--
                  The presets scroll sideways on a narrow screen and stack into
                  a rail beside the calendar on a wide one, so the panel never
                  forces the popover taller than the calendar it belongs to.
                -->
                <div
                    class="flex gap-1 overflow-x-auto border-b p-2 sm:flex-col sm:overflow-x-visible sm:border-r sm:border-b-0"
                >
                    <Button
                        v-if="clearable"
                        :variant="isUnset ? 'secondary' : 'ghost'"
                        size="sm"
                        class="justify-start whitespace-nowrap"
                        :aria-pressed="isUnset"
                        @click="choose('')"
                    >
                        {{ placeholder }}
                    </Button>

                    <Button
                        v-for="option in presets"
                        :key="option.value"
                        :variant="option.value === preset ? 'secondary' : 'ghost'"
                        size="sm"
                        class="justify-start whitespace-nowrap"
                        :aria-pressed="option.value === preset"
                        @click="choose(option.value)"
                    >
                        {{ option.label }}
                    </Button>
                </div>

                <RangeCalendar
                    :model-value="draft"
                    :max-value="maxDate"
                    initial-focus
                    @update:model-value="pick"
                />
            </div>
        </PopoverContent>
    </Popover>
</template>
