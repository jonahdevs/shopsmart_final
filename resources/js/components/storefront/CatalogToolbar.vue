<script setup lang="ts">
import { SlidersHorizontal } from '@lucide/vue';
import { useId } from 'vue';
import {
    CATALOG_SORTS,
    groupNumber,
} from '@/components/storefront/catalogFilters';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';

/**
 * The bar above the grid: how much is showing, how it is ordered, and — below
 * `lg`, where the sidebar is behind a sheet — the way into the filters.
 *
 * It is one of the storefront's cards rather than a floating row, so the
 * listing reads as a panel of controls sitting above a shelf of tiles.
 */
defineProps<{
    shown: number;
    total: number;
    sort: string;
    /** Drives the badge on the mobile trigger, so the sheet advertises itself. */
    activeFilterCount: number;
}>();

const emit = defineEmits<{
    sort: [value: string];
    openFilters: [];
}>();

const sortId = useId();

/**
 * Read the choice off the native `change` event rather than the primitive's
 * `update:modelValue`, whose declared payload type would have to be worked
 * around with a cast — and the `ui/` primitive is never forked to fix that.
 */
function onSortChange(event: Event): void {
    const select = event.target;

    if (select instanceof HTMLSelectElement) {
        emit('sort', select.value);
    }
}
</script>

<template>
    <div
        class="border-rule shadow-card flex flex-wrap items-center justify-between gap-x-4 gap-y-3 rounded-lg border bg-white px-4 py-3"
    >
        <!--
          The count is the only thing on the page that reports the result of a
          filter change, so it is the live region: a partial reload swaps the
          grid without moving focus, and this is what says so.
        -->
        <p class="text-muted-foreground text-sm" aria-live="polite">
            <template v-if="total > 0">
                Showing
                <span class="text-foreground font-medium tabular-nums">
                    {{ groupNumber(shown) }}
                </span>
                of
                <span class="text-foreground font-medium tabular-nums">
                    {{ groupNumber(total) }}
                </span>
                {{ total === 1 ? 'product' : 'products' }}
            </template>
            <template v-else>No products match these filters</template>
        </p>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <button
                type="button"
                class="border-rule hover:border-electric hover:text-electric focus-visible:outline-electric text-ink inline-flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 lg:hidden"
                @click="emit('openFilters')"
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                Filters
                <span
                    v-if="activeFilterCount > 0"
                    class="bg-electric font-display rounded-full px-1.5 py-0.5 text-[0.625rem] font-bold text-white tabular-nums"
                >
                    {{ activeFilterCount }}
                </span>
            </button>

            <div class="flex items-center gap-2">
                <label
                    :for="sortId"
                    class="text-muted-foreground shrink-0 text-sm"
                >
                    Sort
                </label>
                <NativeSelect
                    :id="sortId"
                    :model-value="sort"
                    class="rounded-lg bg-white"
                    @change="onSortChange"
                >
                    <NativeSelectOption
                        v-for="option in CATALOG_SORTS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </NativeSelectOption>
                </NativeSelect>
            </div>
        </div>
    </div>
</template>
