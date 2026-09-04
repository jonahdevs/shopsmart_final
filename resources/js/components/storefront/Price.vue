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
 * unit off the amount without knowing anything about currency rules.
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

    const index = formatted.indexOf(' ');

    return index === -1
        ? { unit: null, amount: formatted }
        : {
              unit: formatted.slice(0, index),
              amount: formatted.slice(index + 1),
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
                class="text-[0.625rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
            >
                {{ parts.unit }}
            </span>
            <span
                :class="
                    cn(
                        'font-display font-extrabold tabular-nums tracking-[-0.02em] text-foreground',
                        amountSize,
                    )
                "
            >
                {{ parts.amount }}
            </span>
        </p>

        <span
            v-if="compareFormatted"
            class="pb-0.5 text-sm text-muted-foreground line-through tabular-nums"
        >
            {{ compareFormatted }}
        </span>

        <span
            v-if="discountPercent"
            class="mb-0.5 rounded-xs bg-sale px-1.5 py-0.5 font-display text-[0.6875rem] font-bold tracking-wide text-white tabular-nums"
        >
            &minus;{{ discountPercent }}%
        </span>
    </div>

    <!-- No price set: the product is sold on request rather than off the shelf. -->
    <p
        v-else
        class="font-display text-sm font-bold tracking-[-0.01em] text-muted-foreground"
    >
        Price on request
    </p>
</template>
