<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * The storefront's signature element.
 *
 * Price is the most-scanned thing on a price-competitive marketplace, so it is
 * where the identity is spent: the currency is demoted to a small tracked unit
 * and the amount is set in the heavy display face with tabular figures, so
 * columns of prices align down a grid. The sale colour appears here and
 * nowhere else in the interface.
 *
 * Amounts arrive preformatted from the server (`money()`), which joins the
 * symbol and the number with a single space — that is what lets this split the
 * unit off the amount without knowing anything about currency rules. The
 * currency's `symbol_position` setting decides which side the symbol lands on
 * (`KES 24,000` or `24,000 KES`), so which token is the unit is worked out from
 * the content rather than assumed to be the first.
 */
const {
    formatted,
    compareFormatted = null,
    discountPercent = null,
    size = 'md',
} = defineProps<{
    formatted: string | null;
    compareFormatted?: string | null;
    discountPercent?: number | null;
    size?: 'sm' | 'md' | 'lg';
}>();

const parts = computed(() => {
    if (!formatted) {
        return null;
    }

    const first = formatted.indexOf(' ');

    if (first === -1) {
        return { unit: null, amount: formatted };
    }

    /*
      A leading token carrying a digit is the amount, so the unit trails it.
      Splitting on the *last* space also keeps a space-grouped amount intact.
    */
    if (/\d/.test(formatted.slice(0, first))) {
        const last = formatted.lastIndexOf(' ');

        return {
            unit: formatted.slice(last + 1),
            amount: formatted.slice(0, last),
        };
    }

    return {
        unit: formatted.slice(0, first),
        amount: formatted.slice(first + 1),
    };
});

const amountSize = computed(
    () =>
        ({
            sm: 'text-lg',
            md: 'text-2xl',
            lg: 'text-4xl sm:text-5xl',
        })[size],
);
</script>

<template>
    <div v-if="parts" class="flex flex-wrap items-end gap-x-2.5 gap-y-1">
        <p class="flex flex-col leading-none">
            <span
                v-if="parts.unit"
                class="text-muted-foreground text-[0.625rem] font-semibold tracking-[0.18em] uppercase"
            >
                {{ parts.unit }}
            </span>
            <span
                :class="
                    cn(
                        'font-display text-foreground font-extrabold tracking-[-0.02em] tabular-nums',
                        amountSize,
                    )
                "
            >
                {{ parts.amount }}
            </span>
        </p>

        <span
            v-if="compareFormatted"
            class="text-muted-foreground pb-0.5 text-sm tabular-nums line-through"
        >
            {{ compareFormatted }}
        </span>

        <span
            v-if="discountPercent"
            class="bg-sale font-display mb-0.5 rounded-xs px-1.5 py-0.5 text-[0.6875rem] font-bold tracking-wide text-white tabular-nums"
        >
            &minus;{{ discountPercent }}%
        </span>
    </div>

    <!-- No price set: the product is sold on request rather than off the shelf. -->
    <p
        v-else
        class="font-display text-muted-foreground text-sm font-bold tracking-[-0.01em]"
    >
        Price on request
    </p>
</template>
