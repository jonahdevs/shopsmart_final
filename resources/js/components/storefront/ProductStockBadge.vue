<script setup lang="ts">
import { Check, CircleDashed, PackageX, Truck } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Availability, stated in the server's own terms.
 *
 * `stockStatus` and `inStock` are decided on the server — nothing here
 * re-derives whether a thing can be bought. The only local judgement is the
 * "only n left" nudge, which is presentation over a quantity the server sent.
 *
 * Every state carries an icon and a word, so none of them is conveyed by
 * colour alone.
 */
const {
    status,
    quantity = null,
    awaitingSelection = false,
} = defineProps<{
    status: App.Enums.StockStatus;
    /** Units left, when the product is tracked; null when it is not. */
    quantity?: number | null;
    /** True while a variable product still needs an option chosen. */
    awaitingSelection?: boolean;
}>();

/** Below this, saying how many are left is useful rather than alarmist. */
const LOW_STOCK_THRESHOLD = 5;

const state = computed(() => {
    if (awaitingSelection) {
        return {
            icon: CircleDashed,
            label: 'Choose an option to see availability',
            tone: 'text-muted-foreground',
        };
    }

    if (status === 'out_of_stock') {
        return {
            icon: PackageX,
            label: 'Out of stock',
            tone: 'text-destructive',
        };
    }

    if (status === 'backorder') {
        return {
            icon: Truck,
            label: 'Available on backorder',
            tone: 'text-muted-foreground',
        };
    }

    return { icon: Check, label: 'In stock', tone: 'text-foreground' };
});

const lowStockNote = computed<string | null>(() => {
    if (awaitingSelection || status !== 'in_stock' || quantity === null) {
        return null;
    }

    return quantity > 0 && quantity <= LOW_STOCK_THRESHOLD
        ? `Only ${quantity} left`
        : null;
});
</script>

<template>
    <p
        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
        :class="state.tone"
    >
        <component
            :is="state.icon"
            class="size-4 shrink-0"
            aria-hidden="true"
        />
        <span>{{ state.label }}</span>
        <span
            v-if="lowStockNote"
            class="font-display text-foreground text-[0.6875rem] font-bold tracking-[0.12em] uppercase"
        >
            &middot; {{ lowStockNote }}
        </span>
    </p>
</template>
